<?php
/**
 * Class LDAPSecurityController
 *
 * This controller overrides the default Security controller with functionality
 * for resetting passwords.
 */
class LDAPSecurityController extends Security {

	/**
	 * @var array
	 */
	private static $allowed_actions = array(
		'index',
		'lostpassword',
		'LostPasswordForm',
		'ChangePasswordForm',
		'passwordsent'
	);

	/**
	 * @param Member $member
	 * @param $autologinToken
	 * @return string
	 */
	public static function getPasswordResetLink($member, $autologinToken) {
		$autologinToken = urldecode($autologinToken);
		$selfControllerClass = __CLASS__;
		$selfController = new $selfControllerClass();
		return $selfController->Link('changepassword') . "?m={$member->ID}&t=$autologinToken";
	}

	/**
	 * Factory method for the lost password form
	 *
	 * @return Form Returns the lost password form
	 */
	public function ChangePasswordForm() {
		return Object::create('LDAPChangePasswordForm', $this, 'ChangePasswordForm');
	}

	/**
	 * Factory method for the lost password form
	 *
	 * @return Form Returns the lost password form
	 */
	public function LostPasswordForm() {
		$email = new EmailField('Email', _t('Member.EMAIL', 'Email'));
		$action = new FormAction('forgotPassword', _t('Security.BUTTONSEND', 'Send me the password reset link'));
		return LDAPLoginForm::create($this,
			'LostPasswordForm',
			new FieldList(array($email)),
			new FieldList(array($action)),
			false
		);
	}

	/**
	 * @param null $action
	 * @return String
	 */
	public function Link($action = null) {
		return Controller::join_links(Director::baseURL(), 'LDAPSecurity', $action);
	}

	/**
	 * Show the "password sent" page, after a user has requested
	 * to reset their password.
	 *
	 * @param SS_HTTPRequest $request The SS_HTTPRequest for this action.
	 * @return string Returns the "password sent" page as HTML code.
	 */
	public function passwordsent($request) {
		$controller = $this->getResponseController(_t('Security.LOSTPASSWORDHEADER', 'Lost Password'));

		// if the controller calls Director::redirect(), this will break early
		if(($response = $controller->getResponse()) && $response->isFinished()) return $response;

		$username = Convert::raw2xml(rawurldecode($request->param('ID')));

		$customisedController = $controller->customise(array(
			'Title' => _t('LDAPSecurity.PASSWORDSENTHEADER', "Password reset link sent to '{username}'",
				array('username' => $username)),
			'Content' =>
				"<p>"
				. _t('LDAPSecurity.PASSWORDSENTTEXT',
					"Thank you! A reset link has been sent to '{username}', provided an account exists.",
					array('username' => $username))
				. "</p>",
			'Username' => $username
		));
		return $customisedController->renderWith($this->getTemplatesFor('passwordsent'));
	}
}
