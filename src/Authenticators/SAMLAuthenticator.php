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
use SilverStripe\Security\MemberAuthenticator\MemberAuthenticator;

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
class SAMLAuthenticator extends MemberAuthenticator
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
     * @inheritdoc
     */
    public function getLoginHandler($link)
    {
        return SAMLLoginHandler::create($link, $this);
    }

    /**
     * @inheritdoc
     */
    public function supportedServices()
    {
        return Authenticator::LOGIN | Authenticator::LOGOUT;
    }
}
