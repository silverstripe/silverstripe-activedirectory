<?php

namespace SilverStripe\ActiveDirectory\Authenticators;

use SilverStripe\ActiveDirectory\Helpers\SAMLHelper;
use SilverStripe\Control\Controller;
use Silverstripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\Authenticator;
use SilverStripe\Security\Member;
use SilverStripe\Security\MemberAuthenticator\LoginHandler;
use SilverStripe\Security\MemberAuthenticator\LogoutHandler;

/**
 * Class SAMLAuthenticator
 *
 * Authenticates the user against a SAML IdP via a single sign-on process.
 * It will create a {@link Member} stub record with rudimentary fields (see {@link SAMLController::acs()})
 * if the Member record was not found.
 *
 * You can either use:
 * - just SAMLAuthenticator (which will trigger LDAP sync anyway, via LDAPMemberExtension::memberLoggedIn)
 * - just LDAPAuthenticator (syncs explicitly, but no single sign-on via IdP done)
 * - both, so people have multiple tabbed options in the login form.
 *
 * Both authenticators understand and collaborate through the GUID field on the Member.
 *
 * @package activedirectory
 */
class SAMLAuthenticator implements Authenticator
{
    /**
     * @var string
     */
    private $name = 'SAML';

    /**
     * @return string
     */
    public static function get_name()
    {
        return Config::inst()->get(self::class, 'name');
    }

    /**
     * @param Controller $controller
     * @return SAMLLoginForm
     */
    public static function get_login_form(Controller $controller)
    {
        return new SAMLLoginForm($controller, 'LoginForm');
    }

    /**
     * Sends the authentication process down the SAML rabbit hole. It will trigger
     * the IdP redirection via the 3rd party implementation, and if successful, the user
     * will be delivered to the SAMLController::acs.
     *
     * @param array $data
     * @param HTTPRequest $request
     * @param ValidationResult|null $result
     * @return bool|Member|void
     * @internal param Form $form
     */
    public function authenticate(array $data, HTTPRequest $request, ValidationResult &$result = null)
    {
        // $data is not used - the form is just one button, with no fields.
        $auth = Injector::inst()->get(SAMLHelper::class)->getSAMLAuth();
        $request->getSession()->set('BackURL', isset($data['BackURL']) ? $data['BackURL'] : null);
        $request->getSession()->save($request);
        $auth->login(Director::absoluteBaseURL().'saml/');
    }

    /**
     * Returns the services supported by this authenticator
     *
     * The number should be a bitwise-OR of 1 or more of the following constants:
     * Authenticator::LOGIN, Authenticator::LOGOUT, Authenticator::CHANGE_PASSWORD,
     * Authenticator::RESET_PASSWORD, or Authenticator::CMS_LOGIN
     *
     * @return int
     */
    public function supportedServices()
    {
        // TODO: Implement supportedServices() method.
    }

    /**
     * Return RequestHandler to manage the log-in process.
     *
     * The default URL of the RequestHandler should return the initial log-in form, any other
     * URL may be added for other steps & processing.
     *
     * URL-handling methods may return an array [ "Form" => (form-object) ] which can then
     * be merged into a default controller.
     *
     * @param string $link The base link to use for this RequestHandler
     * @return LoginHandler
     */
    public function getLoginHandler($link)
    {
        // TODO: Implement getLoginHandler() method.
    }

    /**
     * Return the RequestHandler to manage the log-out process.
     *
     * The default URL of the RequestHandler should log the user out immediately and destroy the session.
     *
     * @param string $link The base link to use for this RequestHandler
     * @return LogoutHandler
     */
    public function getLogOutHandler($link)
    {
        // TODO: Implement getLogOutHandler() method.
    }

    /**
     * Return RequestHandler to manage the change-password process.
     *
     * The default URL of the RequetHandler should return the initial change-password form,
     * any other URL may be added for other steps & processing.
     *
     * URL-handling methods may return an array [ "Form" => (form-object) ] which can then
     * be merged into a default controller.
     *
     * @param string $link The base link to use for this RequestHnadler
     */
    public function getChangePasswordHandler($link)
    {
        // TODO: Implement getChangePasswordHandler() method.
    }

    /**
     * @param string $link
     * @return mixed
     */
    public function getLostPasswordHandler($link)
    {
        // TODO: Implement getLostPasswordHandler() method.
    }

    /**
     * Check if the passed password matches the stored one (if the member is not locked out).
     *
     * Note, we don't return early, to prevent differences in timings to give away if a member
     * password is invalid.
     *
     * @param Member $member
     * @param string $password
     * @param ValidationResult $result
     * @return ValidationResult
     */
    public function checkPassword(Member $member, $password, ValidationResult &$result = null)
    {
        // TODO: Implement checkPassword() method.
    }
}
