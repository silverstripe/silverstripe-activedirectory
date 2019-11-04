<?php
/**
 * Class LDAPAuthenticator.
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
     *
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
     * @param Form  $form
     *
     * @throws SS_HTTPResponse_Exception
     *
     * @return bool|Member|void
     */
    public static function authenticate($data, Form $form = null)
    {
        /** @var LDAPService $service */
        $service = Injector::inst()->get('LDAPService');

        $login = trim($data['Login']);
        $username = $login;
        if (Email::is_valid_address($login)) {
            if (!self::allow_email_logins()) {
                self::form_error_msg($form, _t('LDAPAuthenticator.PLEASEUSEUSERNAME', 'Please enter your username instead of your email to log in.'));

                return;
            }
            $username = $service->getUsernameByEmail($login);

            if (!$username) {
                $fallbackMember = self::fallback_authenticate($data, $form);
                if ($fallbackMember) {
                    return $fallbackMember;
                }

                self::form_error_msg($form, _t('LDAPAuthenticator.INVALIDCREDENTIALS', 'Invalid credentials'));

                return;
            }
        }

        $result = $service->authenticate($username, $data['Password']);
        if (true !== $result['success']) {
            $fallbackMember = self::fallback_authenticate($data, $form);
            if ($fallbackMember) {
                return $fallbackMember;
            }

            self::form_error_msg($form, $result['message']);

            return;
        }

        $identity = $result['identity'];

        $member = self::get_member($identity);
        if (!$member) {
            self::form_error_msg($form, _t('LDAPAuthenticator.PROBLEMFINDINGDATA', 'There was a problem retrieving your user data'));
        }

        Session::clear('BackURL');

        return $member;
    }

    /**
     * Try to authenticate using the fallback authenticator if enabled via config fallback_authenticator.
     *
     * @param array     $data
     * @param Form|null $form
     *
     * @return Member|null
     */
    protected static function fallback_authenticate($data, Form $form = null)
    {
        if ('yes' !== Config::inst()->get('LDAPAuthenticator', 'fallback_authenticator')) {
            return null;
        }

        $authClass = Config::inst()->get('LDAPAuthenticator', 'fallback_authenticator_class');

        SS_Log::log(sprintf('Using fallback authenticator "%s"', $authClass), SS_Log::DEBUG);

        return call_user_func(
            [$authClass, 'authenticate'],
            array_merge($data, ['Email' => $data['Login']]),
            $form
        );
    }

    private static function form_error_msg($form, $message)
    {
        if (!$form) {
            return;
        }

        $form->sessionMessage($message, 'bad');
    }

    /**
     * @return bool
     */
    private static function allow_email_logins()
    {
        return 'yes' === Config::inst()->get('LDAPAuthenticator', 'allow_email_login');
    }

    /**
     * @param string $identity
     * @return Member|null
     */
    private static function get_member($identity)
    {
        /** @var LDAPService $service */
        $service = Injector::inst()->get('LDAPService');

        $data = $service->getUserByUsername($identity);
        if (!$data) {
            return null;
        }

        // LDAPMemberExtension::memberLoggedIn() will update any other AD attributes mapped to Member fields
        /** @var Member $member */
        $member = Member::get()->filter('GUID', $data['objectguid'])->limit(1)->first();
        if (!($member && $member->exists())) {
            $member = new Member();
            $member->GUID = $data['objectguid'];
        }

        // Update the users from LDAP so we are sure that the email is correct.
        // This will also write the Member record.
        $service->updateMemberFromLDAP($member);

        return $member;
    }
}
