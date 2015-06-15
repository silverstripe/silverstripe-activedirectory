<?php
/**
 * Class LDAPLoginForm
 *
 * This not very interesting in itself. It's pretty much boiler-plate code to access the authenticator.
 */
class LDAPLoginForm extends MemberLoginForm {

	/**
	 * This field is used in the "You are logged in as %s" message
	 * @var string
	 */
	public $loggedInAsField = 'FirstName';

	/**
	 * @var string
	 */
	protected $authenticator_class = 'LDAPAuthenticator';

	/**
	 * @var LDAPSecurityController
	 */
	protected $ldapSecController = null;

	/**
	 * Constructor.
	 *
	 * @param Controller $controller
	 * @param string $name method on the $controller
	 * @param FieldList $fields
	 * @param FieldList $actions
	 * @param bool $checkCurrentUser - show logout button if logged in
	 */
	public function __construct($controller, $name, $fields = null, $actions = null, $checkCurrentUser = true) {
		parent::__construct($controller, $name, $fields, $actions, $checkCurrentUser);

		// will be used to get correct Link()
		$this->ldapSecController = Injector::inst()->create('LDAPSecurityController');

		$usernameField = new TextField('Username', _t('Member.USERNAME', 'Username'));
		$this->Fields()->replaceField('Email', $usernameField);
		$this->setValidator(new RequiredFields('Username', 'Password'));
		if(Security::config()->remember_username) {
			$usernameField->setValue(Session::get('SessionForms.MemberLoginForm.Email'));
		} else {
			// Some browsers won't respect this attribute unless it's added to the form
			$this->setAttribute('autocomplete', 'off');
			$usernameField->setAttribute('autocomplete', 'off');
		}

		// Users can't change passwords unless appropriate a LDAP user with write permissions is
		// configured the LDAP connection binding
		$this->Actions()->remove($this->Actions()->fieldByName('forgotPassword'));
		$allowPasswordChange = Config::inst()->get('LDAPService', 'allow_password_change');
		if($allowPasswordChange && $name != 'LostPasswordForm' && !Member::currentUser()) {
			$forgotPasswordLink = sprintf('<p id="ForgotPassword"><a href="%s">%s</a></p>',
				$this->ldapSecController->Link('lostpassword'),
				_t('Member.BUTTONLOSTPASSWORD', "I've lost my password")
			);
			$forgotPassword = new LiteralField('forgotPassword', $forgotPasswordLink);
			$this->Actions()->add($forgotPassword);
		}

		// Focus on the Username field when the page is loaded
		Requirements::block('MemberLoginFormFieldFocus');
		$js = <<<JS
			(function() {
				var el = document.getElementById("Username");
				if(el && el.focus && (typeof jQuery == 'undefined' || jQuery(el).is(':visible'))) el.focus();
			})();
JS;
		Requirements::customScript($js, 'LDAPLoginFormFieldFocus');
	}

	/**
	 * Forgot password form handler method.
	 *
	 * Called when the user clicks on "I've lost my password".
	 *
	 * Extensions can use the 'forgotPassword' method to veto executing
	 * the logic, by returning FALSE. In this case, the user will be redirected back
	 * to the form without further action. It is recommended to set a message
	 * in the form detailing why the action was denied.
	 *
	 * Overridden because we need to generate a link to the LDAPSecurityController
	 * instead of the SecurityController
	 *
	 * @param array $data Submitted data
	 * @return SS_HTTPResponse
	 */
	public function forgotPassword($data) {
		// No need to protect against injections, LDAPService will ensure that this is safe
		$username = trim($data['Username']);

		/**
		 * @var LDAPService
		 */
		$service = Injector::inst()->get('LDAPService');
		$userData = $service->getUserByUsername($username);

		// Avoid information disclosure by displaying the same status,
		// regardless whether the email address actually exists
		if(!isset($userData['objectguid'])) {
			return $this->controller->redirect($this->controller->Link('passwordsent/') . urlencode($data['Username']));
		}

		$member = Member::get()->filter('GUID', $userData['objectguid'])->limit(1)->first();
		// User haven't been imported yet so do that now
		if(!($member && $member->exists())) {
			$member = new Member();
			$member->GUID = $userData['objectguid'];
			$member->write();
		}

		// Allow vetoing forgot password requests
		$results = $this->extend('forgotPassword', $member);
		if($results && is_array($results) && in_array(false, $results, true)) {
			return $this->controller->redirect($this->ldapSecController->Link('lostpassword'));
		}

		// update the users from LDAP so we are sure that the email is correct
		$service->updateMemberFromLDAP($member);

		if($member) {
			$token = $member->generateAutologinTokenAndStoreHash();
			$e = Member_ForgotPasswordEmail::create();
			$e->populateTemplate($member);
			$e->populateTemplate(array(
				'PasswordResetLink' => LDAPSecurityController::getPasswordResetLink($member, $token)
			));
			$e->setTo($member->Email);
			$e->send();
			$this->controller->redirect($this->controller->Link('passwordsent/') . urlencode($data['Username']));
		} elseif($data['Username']) {
			// Avoid information disclosure by displaying the same status,
			// regardless whether the email address actually exists
			$this->controller->redirect($this->controller->Link('passwordsent/') . urlencode($data['Username']));
		} else {
			$this->sessionMessage(
				_t('LDAPLoginForm.ENTERUSERNAME', 'Please enter a username to get a password reset link.'),
				'bad'
			);
			$this->controller->redirect($this->controller->Link('Security'));
		}
	}
}
