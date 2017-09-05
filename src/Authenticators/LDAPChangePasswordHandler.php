<?php

namespace SilverStripe\ActiveDirectory\Authenticators;

use Exception;
use Psr\Log\LoggerInterface;
use SilverStripe\ActiveDirectory\Forms\LDAPChangePasswordForm;
use SilverStripe\ActiveDirectory\Services\LDAPService;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTP;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\Member;
use SilverStripe\Security\MemberAuthenticator\ChangePasswordHandler;
use SilverStripe\Security\Security;

class LDAPChangePasswordHandler extends ChangePasswordHandler
{
    /**
     * @var array Allowed Actions
     */
    private static $allowed_actions = [
        'changepassword',
        'changePasswordForm',
    ];

    /**
     * Factory method for the lost password form
     *
     * @return LDAPChangePasswordForm Returns the lost password form
     */
    public function changePasswordForm()
    {
        return LDAPChangePasswordForm::create($this, 'ChangePasswordForm');
    }

    /**
     * Change the password
     *
     * @param array $data The user submitted data
     * @param LDAPChangePasswordForm $form
     * @return HTTPResponse
     */
    public function doChangePassword(array $data, $form)
    {
        /**
         * @var LDAPService $service
         */
        $service = Injector::inst()->get(LDAPService::class);
        $member = Security::getCurrentUser();
        if ($member) {
            try {
                $userData = $service->getUserByGUID($member->GUID);
            } catch (Exception $e) {
                Injector::inst()->get(LoggerInterface::class)->error($e->getMessage());

                $form->clearMessage();
                $form->sessionMessage(
                    _t(
                        __CLASS__ . '.NOUSER',
                        'Your account hasn\'t been setup properly, please contact an administrator.'
                    ),
                    'bad'
                );
                return $form->getController()->redirect($form->getController()->Link('changepassword'));
            }
            $loginResult = $service->authenticate($userData['samaccountname'], $data['OldPassword']);
            if (!$loginResult['success']) {
                $form->clearMessage();
                $form->sessionMessage(
                    _t(
                        'SilverStripe\\Security\\Member.ERRORPASSWORDNOTMATCH',
                        'Your current password does not match, please try again'
                    ),
                    'bad'
                );
                // redirect back to the form, instead of using redirectBack() which could send the user elsewhere.
                return $form->getController()->redirect($form->getController()->Link('changepassword'));
            }
        }

        if (!$member) {
            if ($this->getRequest()->getSession()->get('AutoLoginHash')) {
                $member = Member::member_from_autologinhash($this->getRequest()->getSession()->get('AutoLoginHash'));
            }

            // The user is not logged in and no valid auto login hash is available
            if (!$member) {
                $this->getRequest()->getSession()->clear('AutoLoginHash');
                return $form->getController()->redirect($form->getController()->Link('login'));
            }
        }

        // Check the new password
        if (empty($data['NewPassword1'])) {
            $form->clearMessage();
            $form->sessionMessage(
                _t(
                    'SilverStripe\\Security\\Member.EMPTYNEWPASSWORD',
                    "The new password can't be empty, please try again"
                ),
                'bad'
            );

            // redirect back to the form, instead of using redirectBack() which could send the user elsewhere.
            return $form->getController()->redirect($form->getController()->Link('changepassword'));
        } elseif ($data['NewPassword1'] == $data['NewPassword2']) {
            // Providing OldPassword to perform password _change_ operation. This will respect the
            // password history policy. Unfortunately we cannot support password history policy on password _reset_
            // at the moment, which means it will not be enforced on SilverStripe-driven email password reset.
            $oldPassword = !empty($data['OldPassword']) ? $data['OldPassword']: null;

            /** @var ValidationResult $validationResult */
            $validationResult = $service->setPassword($member, $data['NewPassword1'], $oldPassword);

            // try to catch connection and other errors that the ldap service can through
            if ($validationResult->isValid()) {
                Security::setCurrentUser($member);

                $this->getRequest()->getSession()->clear('AutoLoginHash');

                // Clear locked out status
                $member->LockedOutUntil = null;
                $member->FailedLoginCount = null;
                $member->write();

                if (!empty($this->getRequest()->requestVar('BackURL'))
                    // absolute redirection URLs may cause spoofing
                    && Director::is_site_url($this->getRequest()->requestVar('BackURL'))
                ) {
                    $url = Director::absoluteURL($this->getRequest()->requestVar('BackURL'));
                    return $form->getController()->redirect($url);
                } else {
                    // Redirect to default location - the login form saying "You are logged in as..."
                    $redirectURL = HTTP::setGetVar(
                        'BackURL',
                        Director::absoluteBaseURL(),
                        $form->getController()->Link('login')
                    );
                    return $form->getController()->redirect($redirectURL);
                }
            } else {
                $form->clearMessage();
                $messages = implode('. ', array_column($validationResult->getMessages(), 'message'));
                $form->sessionMessage($messages, 'bad');
                // redirect back to the form, instead of using redirectBack() which could send the user elsewhere.
                return $form->getController()->redirect($form->getController()->Link('changepassword'));
            }
        } else {
            $form->clearMessage();
            $form->sessionMessage(
                _t(
                    'SilverStripe\\Security\\Member.ERRORNEWPASSWORD',
                    'You have entered your new password differently, try again'
                ),
                'bad'
            );

            // redirect back to the form, instead of using redirectBack() which could send the user elsewhere.
            return $form->getController()->redirect($form->getController()->Link('changepassword'));
        }
    }
}
