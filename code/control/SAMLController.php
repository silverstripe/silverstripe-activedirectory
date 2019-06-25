<?php
/**
 * Class SAMLController
 *
 * This controller handles serving metadata requests for the IdP, as well as handling
 * creating new users and logging them into SilverStripe after being authenticated at the IdP.
 */
class SAMLController extends Controller
{
    /**
     * @var array
     */
    private static $allowed_actions = [
        'index',
        'login',
        'logout',
        'acs',
        'sls',
        'metadata'
    ];

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
    public function acs()
    {
        /** @var OneLogin_Saml2_Auth $auth */
        $auth = Injector::inst()->get('SAMLHelper')->getSAMLAuth();
        $auth->processResponse();

        $error = $auth->getLastErrorReason();
        if (!empty($error)) {
            SS_Log::log($error, SS_Log::ERR);
            Form::messageForForm("SAMLLoginForm_LoginForm", "Authentication error: '{$error}'", 'bad');
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
        if (ctype_print($decodedNameId)) {
            Form::messageForForm("SAMLLoginForm_LoginForm", "Name ID provided by IdP is not a binary GUID.", 'bad');
            Session::save();

            return $this->getRedirect();
        }

        /**
         * If processing reaches here, then the user is authenticated - the rest of this method is just processing their
         * legitimate information and configuring their account.
         */

        // If we expect the NameID to be a binary version of the GUID (ADFS), check that it actually is
        // If we are configured not to expect a binary NameID, then we assume it is a direct GUID (Azure AD)
        if (Config::inst()->get('SAMLConfiguration', 'expect_binary_nameid')) {
            // transform the binary NameId to guid
            $guid = LDAPUtil::bin_to_str_guid($decodedNameId);
        } else {
            $guid = $auth->getNameId();
        }

        if (!LDAPUtil::validGuid($guid)) {
            $errorMessage = "Not a valid GUID '{$guid}' recieved from server.";
            SS_Log::log($errorMessage, SS_Log::ERR);
            Form::messageForForm("SAMLLoginForm_LoginForm", $errorMessage, 'bad');
            Session::save();

            return $this->getRedirect();
        }

        $this->extend('updateGuid', $guid);

        $fieldToClaimMap = array_flip(Member::config()->claims_field_mappings);
        $attributes = $auth->getAttributes();

        // Write a rudimentary member with basic fields on every login, so that we at least have something
        // if there is no further sync (e.g. via LDAP)
        $member = Member::get()->filter('GUID', $guid)->limit(1)->first();
        if (!($member && $member->exists())
            &&  Config::inst()->get('SAMLConfiguration', 'allow_insecure_email_linking')
            && isset($fieldToClaimMap['Email'])
        ) {
            // If there is no member found via GUID and we allow linking via email, search by email
            $member = Member::get()->filter('Email', $attributes[$fieldToClaimMap['Email']])->limit(1)->first();

            if (!($member && $member->exists())) {
                $member = new Member();
            }

            $member->GUID = $guid;
        } elseif (!($member && $member->exists())) {
            // If the member doesn't exist and we don't allow linking via email, then create a new member
            $member = new Member();
            $member->GUID = $guid;
        }

        foreach ($member->config()->claims_field_mappings as $claim => $field) {
            if (!isset($attributes[$claim][0])) {
                SS_Log::log(
                    sprintf(
                        'Claim rule \'%s\' configured in LDAPMember.claims_field_mappings, but wasn\'t passed through. Please check IdP claim rules.',
                        $claim
                    ),
                    SS_Log::WARN
                );

                continue;
            }

            $member->$field = $attributes[$claim][0];
        }

        $member->SAMLSessionIndex = $auth->getSessionIndex();

        $member->write();

        // This will trigger LDAP update through LDAPMemberExtension::memberLoggedIn.
        // The LDAP update will also write the Member record. We shouldn't write before
        // calling this, as any onAfterWrite hooks that attempt to update LDAP won't
        // have the Username field available yet for new Member records, and fail.
        // Both SAML and LDAP identify Members by the GUID field.
        $member->logIn();

        return $this->getRedirect();
    }

    /**
     * Generate this SP's metadata. This is needed for intialising the SP-IdP relationship.
     * IdP is instructed to call us back here to establish the relationship. IdP may also be configured
     * to hit this endpoint periodically during normal operation, to check the SP availability.
     */
    public function metadata()
    {
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
    protected function getRedirect()
    {
        // Absolute redirection URLs may cause spoofing
        if (Session::get('BackURL') && Director::is_site_url(Session::get('BackURL'))) {
            return $this->redirect(Session::get('BackURL'));
        }

        // Spoofing attack, redirect to homepage instead of spoofing url
        if (Session::get('BackURL') && !Director::is_site_url(Session::get('BackURL'))) {
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
