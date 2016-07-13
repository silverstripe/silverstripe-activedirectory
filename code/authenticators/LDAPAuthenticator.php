<?php
/**
 * Class LDAPAuthenticator
 *
 * Authenticate a user against LDAP, without the single sign-on component.
 *
 * See SAMLAuthenticator for further information.
 */
class LDAPAuthenticator extends Authenticator
{
    /**
     * @var string
     */
    private $name = 'LDAP';

    /**
     * Set to 'yes' to indicate if this module should look up usernames in LDAP by matching the email addresses.
     *
     * CAVEAT #1: only set to 'yes' for systems that enforce email uniqueness.
     * Otherwise only the first LDAP user with matching email will be accessible.
     *
     * CAVEAT #2: this is untested for systems that use LDAP with principal style usernames (i.e. foo@bar.com).
     * The system will misunderstand emails for usernames with uncertain outcome.
     *
     * @var string 'no' or 'yes'
     */
    private static $allow_email_login = 'no';

    /**
     * Set to 'yes' to fallback login attempts to {@link $fallback_authenticator}.
     * This will occur if LDAP fails to authenticate the user.
     *
     * @var string 'no' or 'yes'
     */
    private static $fallback_authenticator = 'no';

    /**
     * The class of {@link Authenticator} to use as the fallback authenticator.
     *
     * @var string
     */
    private static $fallback_authenticator_class = 'MemberAuthenticator';

    /**
     * @return string
     */
    public static function get_name()
    {
        return Config::inst()->get('LDAPAuthenticator', 'name');
    }

    /**
     * @param Controller $controller
     * @return LDAPLoginForm
     */
    public static function get_login_form(Controller $controller)
    {
        return new LDAPLoginForm($controller, 'LoginForm');
    }

    /**
     * Performs the login, but will also create and sync the Member record on-the-fly, if not found.
     *
     * @param array $data
     * @param Form $form
     * @return bool|Member|void
     * @throws SS_HTTPResponse_Exception
     */
    public static function authenticate($data, Form $form = null)
    {
        $service = Injector::inst()->get('LDAPService');
        $login = trim($data['Login']);
        if (Email::validEmailAddress($login)) {
            if (Config::inst()->get('LDAPAuthenticator', 'allow_email_login')!='yes') {
                $form->sessionMessage(
                    _t(
                        'LDAPAuthenticator.PLEASEUSEUSERNAME',
                        'Please enter your username instead of your email to log in.'
                    ),
                    'bad'
                );
                return;
            }

            $username = $service->getUsernameByEmail($login);

            // No user found with this email.
            if (!$username) {
                if (Config::inst()->get('LDAPAuthenticator', 'fallback_authenticator') === 'yes') {
                    $fallbackMember = self::fallback_authenticate($data, $form);
                    if ($fallbackMember) {
                        return $fallbackMember;
                    }
                }

                $form->sessionMessage(_t('LDAPAuthenticator.INVALIDCREDENTIALS', 'Invalid credentials'), 'bad');
                return;
            }
        } else {
            $username = $login;
        }

        $result = $service->authenticate($username, $data['Password']);
        $success = $result['success'] === true;
        if (!$success) {
            if (Config::inst()->get('LDAPAuthenticator', 'fallback_authenticator') === 'yes') {
                $fallbackMember = self::fallback_authenticate($data, $form);
                if ($fallbackMember) {
                    return $fallbackMember;
                }
            }

            if ($form) {
                $form->sessionMessage($result['message'], 'bad');
            }
            return;
        }

        $data = $service->getUserByUsername($result['identity']);
        if (!$data) {
            if ($form) {
                $form->sessionMessage(
                    _t('LDAPAuthenticator.PROBLEMFINDINGDATA', 'There was a problem retrieving your user data'),
                    'bad'
                );
            }
            return;
        }

        // LDAPMemberExtension::memberLoggedIn() will update any other AD attributes mapped to Member fields
        $member = Member::get()->filter('GUID', $data['objectguid'])->limit(1)->first();
        if (!($member && $member->exists())) {
            $member = new Member();
            $member->GUID = $data['objectguid'];
        }

        // Update the users from LDAP so we are sure that the email is correct.
        // This will also write the Member record.
        $service->updateMemberFromLDAP($member);

        Session::clear('BackURL');

        return $member;
    }

    /**
     * Try to authenticate using the fallback authenticator.
     *
     * @param array $data
     * @param null|Form $form
     * @return null|Member
     */
    protected static function fallback_authenticate($data, Form $form = null)
    {
        return call_user_func(
            [Config::inst()->get('LDAPAuthenticator', 'fallback_authenticator_class'), 'authenticate'],
            array_merge($data, ['Email' => $data['Login']]),
            $form
        );
    }

}
