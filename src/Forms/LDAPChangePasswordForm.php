<?php

namespace SilverStripe\ActiveDirectory\Forms;

use Exception;
use SilverStripe\ActiveDirectory\Authenticators\LDAPAuthenticator;
use SilverStripe\ActiveDirectory\Services\LDAPService;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTP;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\Member;
use SilverStripe\Security\MemberAuthenticator\ChangePasswordForm;
use SilverStripe\Security\Security;

/**
 * @package activedirectory
 */
class LDAPChangePasswordForm extends ChangePasswordForm
{
    /**
     * The sole purpose for overriding the constructor is surfacing the username to the user.
     * @param \SilverStripe\Control\RequestHandler $controller
     * @param string $name
     * @param FieldList $fields
     * @param FieldList $actions
     */
    public function __construct($controller, $name, $fields = null, $actions = null)
    {
        parent::__construct($controller, $name, $fields, $actions);

        // Obtain the Member object. If the user got this far, they must have already been synced.
        $member = Security::getCurrentUser();
        if (!$member) {
            if ($this->getSession()->get('AutoLoginHash')) {
                $member = Member::member_from_autologinhash($this->getSession()->get('AutoLoginHash'));
            }

            // The user is not logged in and no valid auto login hash is available
            if (!$member) {
                $this->getSession()->clear('AutoLoginHash');
                return $this->controller->redirect($this->controller->Link('login'));
            }
        }

        $data = Injector::inst()
            ->get(LDAPService::class)
            ->getUserByGUID($member->GUID, ['samaccountname']);

        $emailField = null;
        $usernameField = null;
        if (Config::inst()->get(
            LDAPAuthenticator::class,
            'allow_email_login'
        ) === 'yes'
            && !empty($member->Email)
        ) {
            $emailField = TextField::create(
                'Email',
                _t(__CLASS__ . '.USERNAMEOREMAIL', 'Email'),
                $member->Email,
                null,
                $this
            );
        }
        if (!empty($data['samaccountname'])) {
            $usernameField = TextField::create(
                'Username',
                _t(__CLASS__ . '.USERNAME', 'Username'),
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
}
