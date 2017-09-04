<?php

namespace SilverStripe\ActiveDirectory\Forms;

use SilverStripe\ActiveDirectory\Services\LDAPService;
use SilverStripe\Control\RequestHandler;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TextField;
use SilverStripe\Security\MemberAuthenticator\MemberLoginForm;
use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;

/**
 * Class LDAPLoginForm
 *
 * This not very interesting in itself. It's pretty much boiler-plate code to access the authenticator.
 *
 * @package activedirectory
 */
class LDAPLoginForm extends MemberLoginForm
{
    /**
     * This field is used in the "You are logged in as %s" message
     * @var string
     */
    public $loggedInAsField = 'FirstName';

    /**
     * @var string
     */
    protected $authenticator_class = LDAPAuthenticator::class;

    /**
     * Constructor.
     *
     * @param RequestHandler $controller
     * @param string $authenticatorClass
     * @param string $name method on the $controller
     */
    public function __construct(RequestHandler $controller, $authenticatorClass, $name)
    {

        parent::__construct($controller, 'LDAPAuthenticator', $name);

        if (Config::inst()->get(LDAPAuthenticator::class, 'allow_email_login') === 'yes') {
            $loginField = TextField::create(
                'Login',
                _t(__CLASS__ . '.USERNAMEOREMAIL', 'Username or email'),
                null,
                null,
                $this
            );
        } else {
            $loginField = TextField::create('Login', _t(__CLASS__ . '.USERNAME', 'Username'), null, null, $this);
        }

        $this->Fields()->replaceField('Email', $loginField);
        $this->setValidator(new RequiredFields('Login', 'Password'));
        if (Security::config()->remember_username) {
            $loginField->setValue($this->getSession()->get('SessionForms.MemberLoginForm.Email'));
        } else {
            // Some browsers won't respect this attribute unless it's added to the form
            $this->setAttribute('autocomplete', 'off');
            $loginField->setAttribute('autocomplete', 'off');
        }

        // Users can't change passwords unless appropriate a LDAP user with write permissions is
        // configured the LDAP connection binding
        $this->Actions()->remove($this->Actions()->fieldByName('forgotPassword'));
        $allowPasswordChange = Config::inst()
            ->get(LDAPService::class, 'allow_password_change');
        if ($allowPasswordChange && $name != 'LostPasswordForm' && !Security::getCurrentUser()) {
            $forgotPasswordLink = sprintf(
                '<p id="ForgotPassword"><a href="%s">%s</a></p>',
                Security::singleton()->Link('lostpassword'),
                _t('SilverStripe\\Security\\Member.BUTTONLOSTPASSWORD', "I've lost my password")
            );
            $forgotPassword = LiteralField::create('forgotPassword', $forgotPasswordLink);
            $this->Actions()->add($forgotPassword);
        }

        // Focus on the Username field when the page is loaded
        Requirements::block('MemberLoginFormFieldFocus');
        $js = <<<JS
			(function() {
				var el = document.getElementById("Login");
				if(el && el.focus && (typeof jQuery == 'undefined' || jQuery(el).is(':visible'))) el.focus();
			})();
JS;
        Requirements::customScript($js, 'LDAPLoginFormFieldFocus');
    }

    /**
     * The name of this login form, to display in the frontend
     * Replaces Authenticator::get_name()
     *
     * @return string
     */
    public function getAuthenticatorName()
    {
        return _t(__CLASS__ . '.AUTHENTICATORNAME', 'LDAP');
    }
}
