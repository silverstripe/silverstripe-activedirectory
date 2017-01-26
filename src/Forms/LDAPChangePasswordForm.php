<?php

namespace SilverStripe\ActiveDirectory\Forms;

use Exception;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTP;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\TextField;
use SilverStripe\Security\ChangePasswordForm;
use SilverStripe\Security\Member;

/**
 * @package activedirectory
 */
class LDAPChangePasswordForm extends ChangePasswordForm
{
    /**
     * The sole purpose for overriding the constructor is surfacing the username to the user.
     */
    public function __construct($controller, $name, $fields = null, $actions = null)
    {
        parent::__construct($controller, $name, $fields, $actions);

        // Obtain the Member object. If the user got this far, they must have already been synced.
        $member = Member::currentUser();
        if (!$member) {
            if (Session::get('AutoLoginHash')) {
                $member = Member::member_from_autologinhash(Session::get('AutoLoginHash'));
            }

            // The user is not logged in and no valid auto login hash is available
            if (!$member) {
                Session::clear('AutoLoginHash');
                return $this->controller->redirect($this->controller->Link('login'));
            }
        }

        $data = Injector::inst()
            ->get('SilverStripe\\ActiveDirectory\\Services\\LDAPService')
            ->getUserByGUID($member->GUID, ['samaccountname']);

        $emailField = null;
        $usernameField = null;
        if (Config::inst()->get('SilverStripe\\ActiveDirectory\\Authenticators\\LDAPAuthenticator', 'allow_email_login') === 'yes'
            && !empty($member->Email)
        ) {
            $emailField = TextField::create(
                'Email',
                _t('LDAPLoginForm.USERNAMEOREMAIL', 'Email'),
                $member->Email,
                null,
                $this
            );
        }
        if (!empty($data['samaccountname'])) {
            $usernameField = TextField::create(
                'Username',
                _t('LDAPLoginForm.USERNAME', 'Username'),
                $data['samaccountname'],
                null,
                $this
            );
        }

        if ($emailField) {
            $emailFieldReadonly = $emailField->performDisabledTransformation();
            $this->Fields()->unshift($emailFieldReadonly);
        }
        if ($usernameField) {
            $usernameFieldReadonly = $usernameField->performDisabledTransformation();
            $this->Fields()->unshift($usernameFieldReadonly);
        }
    }

    /**
     * Change the password
     *
     * @param array $data The user submitted data
     * @return HTTPResponse
     */
    public function doChangePassword(array $data)
    {
        /**
         * @var LDAPService $service
         */
        $service = Injector::inst()->get('SilverStripe\\ActiveDirectory\\Services\\LDAPService');
        $member = Member::currentUser();
        if ($member) {
            try {
                $userData = $service->getUserByGUID($member->GUID);
            } catch (Exception $e) {
                Injector::inst()->get('Logger')->error($e->getMessage());

                $this->clearMessage();
                $this->sessionMessage(
                    _t(
                        'LDAPAuthenticator.NOUSER',
                        'Your account hasn\'t been setup properly, please contact an administrator.'
                    ),
                    'bad'
                );
                return $this->controller->redirect($this->controller->Link('changepassword'));
            }
            $loginResult = $service->authenticate($userData['samaccountname'], $data['OldPassword']);
            if (!$loginResult['success']) {
                $this->clearMessage();
                $this->sessionMessage(
                    _t('Member.ERRORPASSWORDNOTMATCH', 'Your current password does not match, please try again'),
                    'bad'
                );
                // redirect back to the form, instead of using redirectBack() which could send the user elsewhere.
                return $this->controller->redirect($this->controller->Link('changepassword'));
            }
        }

        if (!$member) {
            if (Session::get('AutoLoginHash')) {
                $member = Member::member_from_autologinhash(Session::get('AutoLoginHash'));
            }

            // The user is not logged in and no valid auto login hash is available
            if (!$member) {
                Session::clear('AutoLoginHash');
                return $this->controller->redirect($this->controller->Link('login'));
            }
        }

        // Check the new password
        if (empty($data['NewPassword1'])) {
            $this->clearMessage();
            $this->sessionMessage(
                _t('Member.EMPTYNEWPASSWORD', "The new password can't be empty, please try again"),
                'bad'
            );

            // redirect back to the form, instead of using redirectBack() which could send the user elsewhere.
            return $this->controller->redirect($this->controller->Link('changepassword'));
        } elseif ($data['NewPassword1'] == $data['NewPassword2']) {
            // Providing OldPassword to perform password _change_ operation. This will respect the
            // password history policy. Unfortunately we cannot support password history policy on password _reset_
            // at the moment, which means it will not be enforced on SilverStripe-driven email password reset.
            $oldPassword = !empty($data['OldPassword']) ? $data['OldPassword']: null;

            /** @var ValidationResult $validationResult */
            $validationResult = $service->setPassword($member, $data['NewPassword1'], $oldPassword);

            // try to catch connection and other errors that the ldap service can through
            if ($validationResult->isValid()) {
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
                } else {
                    // Redirect to default location - the login form saying "You are logged in as..."
                    $redirectURL = HTTP::setGetVar(
                        'BackURL',
                        Director::absoluteBaseURL(),
                        $this->controller->Link('login')
                    );
                    return $this->controller->redirect($redirectURL);
                }
            } else {
                $this->clearMessage();
                $messages = implode('. ', array_column($validationResult->getMessages(), 'message'));
                $this->sessionMessage($messages, 'bad');
                // redirect back to the form, instead of using redirectBack() which could send the user elsewhere.
                return $this->controller->redirect($this->controller->Link('changepassword'));
            }
        } else {
            $this->clearMessage();
            $this->sessionMessage(
                _t('Member.ERRORNEWPASSWORD', 'You have entered your new password differently, try again'),
                'bad'
            );

            // redirect back to the form, instead of using redirectBack() which could send the user elsewhere.
            return $this->controller->redirect($this->controller->Link('changepassword'));
        }
    }
}
