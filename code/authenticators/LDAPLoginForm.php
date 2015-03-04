<?php
/**
 * LDAPLoginForm is not very interesting: it's pretty much a boiler-plate code to access authenticator.
 */
class LDAPLoginForm extends MemberLoginForm {

	/**
	 * This field is used in the "You are logged in as %s" message
	 * @var string
	 */
	public $loggedInAsField = 'FirstName';

	protected $authenticator_class = 'LDAPAuthenticator';

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

		$emailField = new TextField('Username', _t('Member.USERNAME', 'Username'));

		if(Security::config()->remember_username) {
			$emailField->setValue(Session::get('SessionForms.MemberLoginForm.Email'));
		} else {
			// Some browsers won't respect this attribute unless it's added to the form
			$this->setAttribute('autocomplete', 'off');
			$emailField->setAttribute('autocomplete', 'off');
		}

		$this->Fields()->replaceField('Email', $emailField);
		$this->setValidator(new RequiredFields('Email', 'Password'));

		Requirements::block('MemberLoginFormFieldFocus');

		// Focus on the email input when the page is loaded
		$js = <<<JS
			(function() {
				var el = document.getElementById("Username");
				if(el && el.focus && (typeof jQuery == 'undefined' || jQuery(el).is(':visible'))) el.focus();
			})();
JS;
		Requirements::customScript($js, 'LDAPLoginFormFieldFocus');
	}

}
