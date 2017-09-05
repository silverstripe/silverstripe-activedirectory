<?php

namespace SilverStripe\ActiveDirectory\Authenticators;

use SilverStripe\ActiveDirectory\Services\LDAPService;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Member;
use SilverStripe\Security\MemberAuthenticator\LostPasswordForm;
use SilverStripe\Security\MemberAuthenticator\LostPasswordHandler;
use SilverStripe\Security\Security;

class LDAPLostPasswordHandler extends LostPasswordHandler
{
    /**
     * Since the logout and dologin actions may be conditionally removed, it's necessary to ensure these
     * remain valid actions regardless of the member login state.
     *
     * @var array
     * @config
     */
    private static $allowed_actions = [
        'lostpassword',
        'LostPasswordForm',
        'passwordsent',
    ];


    /**
     * @param string $link The URL to recreate this request handler
     * @param LDAPAuthenticator $authenticator
     */
    public function __construct($link, LDAPAuthenticator $authenticator)
    {
        $this->link = $link;
        $this->authenticatorClass = get_class($authenticator);
        parent::__construct($link);
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
     * @param array $data Submitted data
     * @param LostPasswordForm $form
     * @return HTTPResponse
     */
    public function forgotPassword($data, $form)
    {
        /** @var Controller $controller */
        $controller = $form->getController();

        // No need to protect against injections, LDAPService will ensure that this is safe
        $login = trim($data['Login']);

        $service = Injector::inst()->get(LDAPService::class);
        if (Email::is_valid_address($login)) {
            if (Config::inst()->get(LDAPAuthenticator::class, 'allow_email_login') != 'yes') {
                $form->sessionMessage(
                    _t(
                        'SilverStripe\\ActiveDirectory\\Forms\\LDAPLoginForm.USERNAMEINSTEADOFEMAIL',
                        'Please enter your username instead of your email to get a password reset link.'
                    ),
                    'bad'
                );
                return $controller->redirect($controller->Link('lostpassword'));
            }
            $userData = $service->getUserByEmail($login);
        } else {
            $userData = $service->getUserByUsername($login);
        }
        // Avoid information disclosure by displaying the same status,
        // regardless whether the email address actually exists
        if (!isset($userData['objectguid'])) {
            return $controller->redirect($controller->Link('passwordsent/')
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
        $service->updateMemberFromLDAP($member, $userData, false);

        // Allow vetoing forgot password requests
        $results = $this->extend('forgotPassword', $member);
        if ($results && is_array($results) && in_array(false, $results, true)) {
            return $controller->redirect('lostpassword');
        }

        if ($member) {
            /** @see MemberLoginForm::forgotPassword */
            $token = $member->generateAutologinTokenAndStoreHash();
            $e = Email::create()
                ->setSubject(
                    _t(
                        'Silverstripe\\Security\\Member.SUBJECTPASSWORDRESET',
                        'Your password reset link',
                        'Email subject'
                    )
                )
                ->setHTMLTemplate('SilverStripe\\Control\\Email\\ForgotPasswordEmail')
                ->setData($member)
                ->setData(['PasswordResetLink' => Security::getPasswordResetLink($member, $token)]);
            $e->setTo($member->Email);
            $e->send();
            return $controller->redirect($controller->Link('passwordsent/') . urlencode($data['Login']));
        } elseif ($data['Login']) {
            // Avoid information disclosure by displaying the same status,
            // regardless whether the email address actually exists
            return $controller->redirect($controller->Link('passwordsent/') . urlencode($data['Login']));
        } else {
            if (Config::inst()->get(LDAPAuthenticator::class, 'allow_email_login') === 'yes') {
                $form->sessionMessage(
                    _t(
                        'SilverStripe\\ActiveDirectory\\Forms\\LDAPLoginForm.ENTERUSERNAMEOREMAIL',
                        'Please enter your username or your email address to get a password reset link.'
                    ),
                    'bad'
                );
            } else {
                $form->sessionMessage(
                    _t(
                        'SilverStripe\\ActiveDirectory\\Forms\\LDAPLoginForm.ENTERUSERNAME',
                        'Please enter your username to get a password reset link.'
                    ),
                    'bad'
                );
            }
            return $controller->redirect($controller->Link('lostpassword'));
        }
    }

    /**
     * Factory method for the lost password form
     *
     * @return Form Returns the lost password form
     */
    public function lostPasswordForm()
    {
        $loginFieldLabel = (Config::inst()->get(LDAPAuthenticator::class, 'allow_email_login') === 'yes') ?
            _t('SilverStripe\\ActiveDirectory\\Forms\\LDAPLoginForm.USERNAMEOREMAIL', 'Username or email') :
            _t('SilverStripe\\ActiveDirectory\\Forms\\LDAPLoginForm.USERNAME', 'Username');
        $loginField = TextField::create('Login', $loginFieldLabel);

        $action = FormAction::create(
            'forgotPassword',
            _t('SilverStripe\\Security\\Security.BUTTONSEND', 'Send me the password reset link')
        );
        return LostPasswordForm::create(
            $this,
            $this->authenticatorClass,
            'LostPasswordForm',
            FieldList::create([$loginField]),
            FieldList::create([$action]),
            false
        );
    }

    public function lostpassword()
    {
        if (Config::inst()->get(LDAPAuthenticator::class, 'allow_email_login') === 'yes') {
            $message = _t(
                __CLASS__ . '.NOTERESETPASSWORDUSERNAMEOREMAIL',
                'Enter your username or your email address and we will send you a link with which '
                . 'you can reset your password'
            );
        } else {
            $message = _t(
                __CLASS__ . '.NOTERESETPASSWORDUSERNAME',
                'Enter your username and we will send you a link with which you can reset your password'
            );
        }

        return [
            'Content' => DBField::create_field('HTMLFragment', "<p>$message</p>"),
            'Form' => $this->lostPasswordForm(),
        ];
    }

    public function passwordsent()
    {
        $username = Convert::raw2xml(
            rawurldecode($this->getRequest()->param('OtherID'))
        );
        $username .= ($extension = $this->request->getExtension()) ? '.' . $extension : '';

        return [
            'Title' => _t(
                __CLASS__ . '.PASSWORDSENTHEADER',
                "Password reset link sent to '{username}'",
                ['username' => $username]
            ),
            'Content' =>
                _t(
                    __CLASS__ . '.PASSWORDSENTTEXT',
                    "Thank you! A reset link has been sent to '{username}', provided an account exists.",
                    ['username' => $username]
                ),
            'Username' => $username
        ];
    }
}
