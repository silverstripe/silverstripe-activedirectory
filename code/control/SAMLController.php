<?php

/**
 * Class SAMLController
 *
 * @todo(stig) check if sessions from the SAML lib interferes with the Silverstripe ones
 */
class SAMLController extends Controller {

	/**
	 * @var array
	 */
	private static $allowed_actions = array(
		'index',
		'login',
		'logout',
		'acs',
		'sls',
		'metadata',
	);

	/**
	 * Assertion Consumer Service
	 *
	 * The user gets sent back here after authenticating with the IdP, off-site.
	 * The earlier redirection to the IdP can be found in the SAMLAuthenticator::authenticate.
	 *
	 * After this handler completes, we end up with a rudimentary Member record (which will be created on-the-fly
	 * if not existent), with the user already logged in. Login triggers memberLoggedIn hooks, which allows
	 * LDAP side of this module to finish off loading Member data.
	 *
	 * @throws OneLogin_Saml2_Error
	 */
	public function acs() {
		/**
		 * @var OneLogin_Saml2_Auth
		 */

		$auth = Injector::inst()->get('SAMLHelper')->getSAMLAuth();
		$auth->processResponse();

		$errors = $auth->getErrors();
		if (!empty($errors)) {
			$errorMessages = implode(', ', $errors);
			SS_Log::log($errorMessages, SS_Log::ERR);
			Form::messageForForm("SAMLLoginForm_LoginForm", "Authentication error: '{$errorMessages}'", 'bad');
			Session::save();
			return $this->getRedirect();
		}

		if (!$auth->isAuthenticated()) {
			Form::messageForForm("SAMLLoginForm_LoginForm", _t('Member.ERRORWRONGCRED'), 'bad');
			Session::save();
			return $this->getRedirect();
		}

		$decodedNameId = base64_decode($auth->getNameId());
		// check that the NameID is a binary string (which signals that it is a guid
		if(ctype_print($decodedNameId)){
			Form::messageForForm("SAMLLoginForm_LoginForm", "Name ID provided by IdP is not a binary GUID.", 'bad');
			Session::save();
			return $this->getRedirect();
		};

		// transform the NameId to guid
		$guid = LDAPUtil::bin_to_str_guid($decodedNameId);
		if(!LDAPUtil::validGuid($guid)) {
			$errorMessage = "Not a valid GUID '{$guid}' recieved from server.";
			SS_Log::log($errorMessage, SS_Log::ERR);
			Form::messageForForm("SAMLLoginForm_LoginForm", $errorMessage, 'bad');
			Session::save();
			return $this->getRedirect();
		}

		// Write a rudimentary member with basic fields on every login, so that we at least have something
		// if LDAP synchronisation fails.
		$member = Member::get()->filter('GUID', $guid)->limit(1)->first();
		if(!$member) {
			$member = new Member();
			$member->GUID = $guid;
		}

		$attributes = $auth->getAttributes();

		// Availability of these is controlled by the "claim rules" on the IdP side. Not all data
		// can be provided this way, so we rely on LDAP to fill in the blanks / overwrite fields later.
		$member->Email = $attributes['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress'][0];
		$member->FirstName = $attributes['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname'][0];
		$member->Surname = $attributes['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname'][0];
		$member->SAMLSessionIndex = $auth->getSessionIndex();

		// This will throw an exception if there are two distinct GUIDs with the same email address.
		// We are happy with a raw 500 here at this stage.
		$member->write();

		// This will trigger LDAP update through LDAPMemberExtension::memberLoggedIn.
		// Both SAML and LDAP identify Members by the GUID field.
		$member->logIn();

		return $this->getRedirect();
	}

	/**
	 * Generate this SP's metadata. This is needed for intialising the SP-IdP relationship.
	 * IdP is instructed to call us back here to establish the relationship. IdP may also be configured
	 * to hit this endpoint periodically during normal operation, to check the SP availability.
	 */
	public function metadata() {
		try {
			$auth = Injector::inst()->get('SAMLHelper')->getSAMLAuth();
			$settings = $auth->getSettings();
			$metadata = $settings->getSPMetadata();
			$errors = $settings->validateMetadata($metadata);
			if (empty($errors)) {
				header('Content-Type: text/xml');
				echo $metadata;
			} else {
				throw new \OneLogin_Saml2_Error(
					'Invalid SP metadata: ' . implode(', ', $errors),
					\OneLogin_Saml2_Error::METADATA_SP_INVALID
				);
			}
		} catch (Exception $e) {
			SS_Log::log($e->getMessage(), SS_Log::ERR);
			echo $e->getMessage();
		}
	}

	/**
	 * @return SS_HTTPResponse
	 */
	protected function getRedirect() {
		// Absolute redirection URLs may cause spoofing
		if(Session::get('BackURL') && Director::is_site_url(Session::get('BackURL')) ) {
			return $this->redirect(Session::get('BackURL'));
		}

		// Spoofing attack, redirect to homepage instead of spoofing url
		if(Session::get('BackURL') && !Director::is_site_url(Session::get('BackURL'))) {
			return $this->redirect(Director::absoluteBaseURL());
		}

		// If a default login dest has been set, redirect to that.
		if (Security::config()->default_login_dest) {
			return $this->redirect(Director::absoluteBaseURL() . Security::config()->default_login_dest);
		}

		// fallback to redirect back to home page
		return $this->redirect(Director::absoluteBaseURL());
	}

}
