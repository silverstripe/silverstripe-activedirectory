<?php

class LDAPChangePasswordForm extends ChangePasswordForm {

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
