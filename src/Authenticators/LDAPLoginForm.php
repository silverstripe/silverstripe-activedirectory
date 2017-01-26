<?php

namespace SilverStripe\ActiveDirectory\Authenticators;

use SilverStripe\ActiveDirectory\Control\LDAPSecurityController;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TextField;
use SilverStripe\Security\Member;
use SilverStripe\Security\MemberLoginForm;
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
    protected $authenticator_class = 'SilverStripe\\ActiveDirectory\\Authenticators\\LDAPAuthenticator';

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
    public function __construct($controller, $name, $fields = null, $actions = null, $checkCurrentUser = true)
    {
        parent::__construct($controller, $name, $fields, $actions, $checkCurrentUser);

        // will be used to get correct Link()
        $this->ldapSecController = Injector::inst()->create('SilverStripe\\ActiveDirectory\\Control\\LDAPSecurityController');

        if (Config::inst()->get('SilverStripe\\ActiveDirectory\\Authenticators\\LDAPAuthenticator', 'allow_email_login')==='yes') {
            $loginField = TextField::create(
                'Login',
                _t('LDAPLoginForm.USERNAMEOREMAIL', 'Username or email'),
                null,
                null,
                $this
            );
        } else {
            $loginField = TextField::create('Login', _t('LDAPLoginForm.USERNAME', 'Username'), null, null, $this);
        }

        $this->Fields()->replaceField('Email', $loginField);
        $this->setValidator(new RequiredFields('Login', 'Password'));
        if (Security::config()->remember_username) {
            $loginField->setValue(Session::get('SessionForms.MemberLoginForm.Email'));
        } else {
            // Some browsers won't respect this attribute unless it's added to the form
            $this->setAttribute('autocomplete', 'off');
            $loginField->setAttribute('autocomplete', 'off');
        }

        // Users can't change passwords unless appropriate a LDAP user with write permissions is
        // configured the LDAP connection binding
        $this->Actions()->remove($this->Actions()->fieldByName('forgotPassword'));
        $allowPasswordChange = Config::inst()
            ->get('SilverStripe\\ActiveDirectory\\Services\\LDAPService', 'allow_password_change');
        if ($allowPasswordChange && $name != 'LostPasswordForm' && !Member::currentUser()) {
            $forgotPasswordLink = sprintf(
                '<p id="ForgotPassword"><a href="%s">%s</a></p>',
                $this->ldapSecController->Link('lostpassword'),
                _t('Member.BUTTONLOSTPASSWORD', "I've lost my password")
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
    public function forgotPassword($data)
    {
        // No need to protect against injections, LDAPService will ensure that this is safe
        $login = trim($data['Login']);

        $service = Injector::inst()->get('SilverStripe\\ActiveDirectory\\Services\\LDAPService');
        if (Email::is_valid_address($login)) {
            if (Config::inst()->get('SilverStripe\\ActiveDirectory\\Authenticators\\LDAPAuthenticator', 'allow_email_login') != 'yes') {
                $this->sessionMessage(
                    _t(
                        'LDAPLoginForm.USERNAMEINSTEADOFEMAIL',
                        'Please enter your username instead of your email to get a password reset link.'
                    ),
                    'bad'
                );
                $this->controller->redirect($this->controller->Link('lostpassword'));
                return;
            }
            $userData = $service->getUserByEmail($login);
        } else {
            $userData = $service->getUserByUsername($login);
        }

        // Avoid information disclosure by displaying the same status,
        // regardless whether the email address actually exists
        if (!isset($userData['objectguid'])) {
            return $this->controller->redirect($this->controller->Link('passwordsent/')
                . urlencode($data['Login']));
        }

        $member = Member::get()->filter('GUID', $userData['objectguid'])->limit(1)->first();
        // User haven't been imported yet so do that now
        if (!($member && $member->exists())) {
            $member = new Member();
            $member->GUID = $userData['objectguid'];
        }

        // Update the users from LDAP so we are sure that the email is correct.
        // This will also write the Member record.
        $service->updateMemberFromLDAP($member);

        // Allow vetoing forgot password requests
        $results = $this->extend('forgotPassword', $member);
        if ($results && is_array($results) && in_array(false, $results, true)) {
            return $this->controller->redirect($this->ldapSecController->Link('lostpassword'));
        }

        if ($member) {
            /** @see MemberLoginForm::forgotPassword */
            $token = $member->generateAutologinTokenAndStoreHash();
            $e = Email::create();
            $e->setSubject(_t('Member.SUBJECTPASSWORDRESET', 'Your password reset link', 'Email subject'));
            $e->setTemplate('ForgotPasswordEmail');
            $e->populateTemplate($member);
            $e->populateTemplate([
                'PasswordResetLink' => LDAPSecurityController::getPasswordResetLink($member, $token)
            ]);
            $e->setTo($member->Email);
            $e->send();
            $this->controller->redirect($this->controller->Link('passwordsent/') . urlencode($data['Login']));
        } elseif ($data['Login']) {
            // Avoid information disclosure by displaying the same status,
            // regardless whether the email address actually exists
            $this->controller->redirect($this->controller->Link('passwordsent/') . urlencode($data['Login']));
        } else {
            if (Config::inst()->get('SilverStripe\\ActiveDirectory\\Authenticators\\LDAPAuthenticator', 'allow_email_login') === 'yes') {
                $this->sessionMessage(
                    _t(
                        'LDAPLoginForm.ENTERUSERNAMEOREMAIL',
                        'Please enter your username or your email address to get a password reset link.'
                    ),
                    'bad'
                );
            } else {
                $this->sessionMessage(
                    _t(
                        'LDAPLoginForm.ENTERUSERNAME',
                        'Please enter your username to get a password reset link.'
                    ),
                    'bad'
                );
            }
            $this->controller->redirect($this->controller->Link('lostpassword'));
        }
    }
}
