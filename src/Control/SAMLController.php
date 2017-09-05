<?php

namespace SilverStripe\ActiveDirectory\Control;

use Exception;
use OneLogin_Saml2_Error;
use Psr\Log\LoggerInterface;
use SilverStripe\ActiveDirectory\Authenticators\SAMLLoginForm;
use SilverStripe\ActiveDirectory\Helpers\SAMLHelper;
use SilverStripe\ActiveDirectory\Model\LDAPUtil;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * Class SAMLController
 *
 * This controller handles serving metadata requests for the IdP, as well as handling
 * creating new users and logging them into SilverStripe after being authenticated at the IdP.
 *
 * @package activedirectory
 */
class SAMLController extends Controller
{
    /**
     * @var array
     */
    private static $allowed_actions = [
        'index',
        'login',
        'logout',
        'acs',
        'sls',
        'metadata'
    ];

    /**
     * Assertion Consumer Service
     *
     * The user gets sent back here after authenticating with the IdP, off-site.
     * The earlier redirection to the IdP can be found in the SAMLAuthenticator::authenticate.
     *
     * After this handler completes, we end up with a rudimentary Member record (which will be created on-the-fly
     * if not existent), with the user already logged in. Login triggers memberLoggedIn hooks, which allows
     * LDAP side of this module to finish off loading Member data.
     *
     * @throws OneLogin_Saml2_Error
     */
    public function acs()
    {
        $auth = Injector::inst()->get(SAMLHelper::class)->getSAMLAuth();
        $auth->processResponse();

        $error = $auth->getLastErrorReason();
        if (!empty($error)) {
            $this->getLogger()->error($error);

            $this->getForm()->sessionMessage("Authentication error: '{$error}'", 'bad');
            $this->getRequest()->getSession()->save($this->getRequest());
            return $this->getRedirect();
        }

        if (!$auth->isAuthenticated()) {
            $this->getForm()->sessionMessage(_t('SilverStripe\\Security\\Member.ERRORWRONGCRED'), 'bad');
            $this->getRequest()->getSession()->save($this->getRequest());
            return $this->getRedirect();
        }

        $decodedNameId = base64_decode($auth->getNameId());
        // check that the NameID is a binary string (which signals that it is a guid
        if (ctype_print($decodedNameId)) {
            $this->getForm()->sessionMessage('Name ID provided by IdP is not a binary GUID.', 'bad');
            $this->getRequest()->getSession()->save($this->getRequest());
            return $this->getRedirect();
        }

        // transform the NameId to guid
        $guid = LDAPUtil::bin_to_str_guid($decodedNameId);
        if (!LDAPUtil::validGuid($guid)) {
            $errorMessage = "Not a valid GUID '{$guid}' recieved from server.";
            $this->getLogger()->error($errorMessage);
            $this->getForm()->sessionMessage($errorMessage, 'bad');
            $this->getRequest()->getSession()->save($this->getRequest());
            return $this->getRedirect();
        }

        // Write a rudimentary member with basic fields on every login, so that we at least have something
        // if LDAP synchronisation fails.
        $member = Member::get()->filter('GUID', $guid)->limit(1)->first();
        if (!($member && $member->exists())) {
            $member = new Member();
            $member->GUID = $guid;
        }

        $attributes = $auth->getAttributes();

        foreach ($member->config()->claims_field_mappings as $claim => $field) {
            if (!isset($attributes[$claim][0])) {
                $this->getLogger()->warning(
                    sprintf(
                        'Claim rule \'%s\' configured in LDAPMember.claims_field_mappings, ' .
                                'but wasn\'t passed through. Please check IdP claim rules.',
                        $claim
                    )
                );

                continue;
            }

            $member->$field = $attributes[$claim][0];
        }

        $member->SAMLSessionIndex = $auth->getSessionIndex();

        // This will trigger LDAP update through LDAPMemberExtension::memberLoggedIn.
        // The LDAP update will also write the Member record. We shouldn't write before
        // calling this, as any onAfterWrite hooks that attempt to update LDAP won't
        // have the Username field available yet for new Member records, and fail.
        // Both SAML and LDAP identify Members by the GUID field.
        Security::setCurrentUser($member);

        return $this->getRedirect();
    }

    /**
     * Generate this SP's metadata. This is needed for intialising the SP-IdP relationship.
     * IdP is instructed to call us back here to establish the relationship. IdP may also be configured
     * to hit this endpoint periodically during normal operation, to check the SP availability.
     */
    public function metadata()
    {
        try {
            $auth = Injector::inst()->get('SilverStripe\\ActiveDirectory\\Helpers\\SAMLHelper')->getSAMLAuth();
            $settings = $auth->getSettings();
            $metadata = $settings->getSPMetadata();
            $errors = $settings->validateMetadata($metadata);
            if (empty($errors)) {
                header('Content-Type: text/xml');
                echo $metadata;
            } else {
                throw new \OneLogin_Saml2_Error(
                    'Invalid SP metadata: ' . implode(', ', $errors),
                    \OneLogin_Saml2_Error::METADATA_SP_INVALID
                );
            }
        } catch (Exception $e) {
            $this->getLogger()->error($e->getMessage());
            echo $e->getMessage();
        }
    }

    /**
     * @return HTTPResponse
     */
    protected function getRedirect()
    {
        // Absolute redirection URLs may cause spoofing
        if ($this->getRequest()->getSession()->get('BackURL')
            && Director::is_site_url($this->getRequest()->getSession()->get('BackURL'))) {
            return $this->redirect($this->getRequest()->getSession()->get('BackURL'));
        }

        // Spoofing attack, redirect to homepage instead of spoofing url
        if ($this->getRequest()->getSession()->get('BackURL')
            && !Director::is_site_url($this->getRequest()->getSession()->get('BackURL'))) {
            return $this->redirect(Director::absoluteBaseURL());
        }

        // If a default login dest has been set, redirect to that.
        if (Security::config()->default_login_dest) {
            return $this->redirect(Director::absoluteBaseURL() . Security::config()->default_login_dest);
        }

        // fallback to redirect back to home page
        return $this->redirect(Director::absoluteBaseURL());
    }

    /**
     * Get a logger
     *
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return Injector::inst()->get(LoggerInterface::class);
    }

    /**
     * Gets the login form
     *
     * @return SAMLLoginForm
     */
    public function getForm()
    {
        return Injector::inst()->get(SAMLLoginForm::class);
    }
}
