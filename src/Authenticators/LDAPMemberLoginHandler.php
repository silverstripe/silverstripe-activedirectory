<?php

namespace SilverStripe\ActiveDirectory\Authenticators;

use SilverStripe\ActiveDirectory\Services\LDAPService;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\Member;
use SilverStripe\Security\MemberLoginHandler;

class LDAPMemberLoginHandler extends MemberLoginHandler
{
    /**
     * @var string
     */
    protected $authenticator_class = LDAPAuthenticator::class;

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
        /** @var Controller $controller */
        $controller = $this->form->getController();

        // No need to protect against injections, LDAPService will ensure that this is safe
        $login = trim($data['Login']);

        $service = Injector::inst()->get(LDAPService::class);
        if (Email::is_valid_address($login)) {
            if (Config::inst()->get(LDAPAuthenticator::class, 'allow_email_login') != 'yes') {
                $this->sessionMessage(
                    _t(
                        'LDAPLoginForm.USERNAMEINSTEADOFEMAIL',
                        'Please enter your username instead of your email to get a password reset link.'
                    ),
                    'bad'
                );
                $controller->redirect($controller->Link('lostpassword'));
                return;
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
        $service->updateMemberFromLDAP($member);

        // Allow vetoing forgot password requests
        $results = $this->extend('forgotPassword', $member);
        if ($results && is_array($results) && in_array(false, $results, true)) {
            return $controller->redirect($this->ldapSecController->Link('lostpassword'));
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
            $controller->redirect($controller->Link('passwordsent/') . urlencode($data['Login']));
        } elseif ($data['Login']) {
            // Avoid information disclosure by displaying the same status,
            // regardless whether the email address actually exists
            $controller->redirect($controller->Link('passwordsent/') . urlencode($data['Login']));
        } else {
            if (Config::inst()->get(LDAPAuthenticator::class, 'allow_email_login') === 'yes') {
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
            $controller->redirect($controller->Link('lostpassword'));
        }
    }
}
