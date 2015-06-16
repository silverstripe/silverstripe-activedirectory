<?php

class LDAPChangePasswordForm extends ChangePasswordForm {

	/**
	 * The sole purpose for overriding the constructor is surfacing the username to the user.
	 */
	public function __construct($controller, $name, $fields = null, $actions = null) {
		parent::__construct($controller, $name, $fields, $actions);

		// Obtain the Member object. If the user got this far, they must have already been synced.
		$member = Member::currentUser();
		if(!$member) {
			if(Session::get('AutoLoginHash')) {
				$member = Member::member_from_autologinhash(Session::get('AutoLoginHash'));
			}

			// The user is not logged in and no valid auto login hash is available
			if(!$member) {
				Session::clear('AutoLoginHash');
				return $this->controller->redirect($this->controller->Link('login'));
			}
		}

		// Get the username.
		$ldap = Injector::inst()->get('LDAPService')->getUserByGUID($member->GUID, array('samaccountname'));

		if (!empty($ldap['samaccountname'])) {
			$usernameField = new TextField('Username', 'Username', $ldap['samaccountname']);
			$usernameField = $usernameField->performDisabledTransformation();
			$this->Fields()->unshift($usernameField);
		}
	}

	/**
	 * Change the password
	 *
	 * @param array $data The user submitted data
	 * @return SS_HTTPResponse
	 */
	public function doChangePassword(array $data) {

		/**
		 * @var LDAPService $service
		 */
		$service = Injector::inst()->get('LDAPService');

		if($member = Member::currentUser()) {
			try {
				$userData = $service->getUserByGUID($member->GUID);
			} catch(Exception $e) {
				SS_Log::log($e->getMessage(), SS_Log::ERR);
				$this->clearMessage();
				$this->sessionMessage(
					_t('LDAPAuthenticator.NOUSER', 'Your account hasn\'t been setup properly, please contact an administrator.'),
					'bad'
				);
				return $this->controller->redirect($this->controller->Link('changepassword'));
			}
			$loginResult = $service->authenticate($userData['samaccountname'], $data['OldPassword']);
			if(!$loginResult['success']) {
				$this->clearMessage();
				$this->sessionMessage(
					_t('Member.ERRORPASSWORDNOTMATCH', "Your current password does not match, please try again"),
					"bad"
				);
				// redirect back to the form, instead of using redirectBack() which could send the user elsewhere.
				return $this->controller->redirect($this->controller->Link('changepassword'));
			}
		}

		if(!$member) {
			if(Session::get('AutoLoginHash')) {
				$member = Member::member_from_autologinhash(Session::get('AutoLoginHash'));
			}

			// The user is not logged in and no valid auto login hash is available
			if(!$member) {
				Session::clear('AutoLoginHash');
				return $this->controller->redirect($this->controller->Link('login'));
			}
		}

		// Check the new password
		if(empty($data['NewPassword1'])) {
			$this->clearMessage();
			$this->sessionMessage(
				_t('Member.EMPTYNEWPASSWORD', "The new password can't be empty, please try again"),
				"bad");

			// redirect back to the form, instead of using redirectBack() which could send the user elsewhere.
			return $this->controller->redirect($this->controller->Link('changepassword'));
		}
		else if($data['NewPassword1'] == $data['NewPassword2']) {
			$isValid = $service->setPassword($member, $data['NewPassword1']);
			// try to catch connection and other errors that the ldap service can through

			if($isValid->valid()) {
				$member->logIn();

				Session::clear('AutoLoginHash');

				// Clear locked out status
				$member->LockedOutUntil = null;
				$member->FailedLoginCount = null;
				$member->write();

				if (!empty($_REQUEST['BackURL'])
					// absolute redirection URLs may cause spoofing
					&& Director::is_site_url($_REQUEST['BackURL'])
				) {
					$url = Director::absoluteURL($_REQUEST['BackURL']);
					return $this->controller->redirect($url);
				}
				else {
					// Redirect to default location - the login form saying "You are logged in as..."
					$redirectURL = HTTP::setGetVar(
						'BackURL',
						Director::absoluteBaseURL(), $this->controller->Link('login')
					);
					return $this->controller->redirect($redirectURL);
				}
			} else {
				$this->clearMessage();
				$this->sessionMessage($isValid->message(), "bad");
				// redirect back to the form, instead of using redirectBack() which could send the user elsewhere.
				return $this->controller->redirect($this->controller->Link('changepassword'));
			}

		} else {
			$this->clearMessage();
			$this->sessionMessage(
				_t('Member.ERRORNEWPASSWORD', "You have entered your new password differently, try again"),
				"bad");

			// redirect back to the form, instead of using redirectBack() which could send the user elsewhere.
			return $this->controller->redirect($this->controller->Link('changepassword'));
		}
	}

}
