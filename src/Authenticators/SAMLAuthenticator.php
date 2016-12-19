<?php

namespace SilverStripe\ActiveDirectory\Authenticators;

use SilverStripe\Control\Controller;
use Silverstripe\Control\Director;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\Form;
use SilverStripe\Security\Authenticator;

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
class SAMLAuthenticator extends Authenticator
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
        return Config::inst()->get('SilverStripe\\ActiveDirectory\\Authenticators\\SAMLAuthenticator', 'name');
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
     * @param Form $form
     * @return bool|Member|void
     * @throws SS_HTTPResponse_Exception
     */
    public static function authenticate($data, Form $form = null)
    {
        // $data is not used - the form is just one button, with no fields.
        $auth = Injector::inst()->get('SilverStripe\\ActiveDirectory\\Helpers\\SAMLHelper')->getSAMLAuth();
        Session::set('BackURL', isset($data['BackURL']) ? $data['BackURL'] : null);
        Session::save();
        $auth->login(Director::absoluteBaseURL().'saml/');
    }
}
