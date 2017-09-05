<?php

namespace SilverStripe\ActiveDirectory\Authenticators;

use SilverStripe\Control\RequestHandler;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\FormAction;
use SilverStripe\Security\LoginForm;
use SilverStripe\Security\Security;

/**
 * Class SAMLLoginForm
 *
 * This not very interesting in itself. It's pretty much boiler-plate code to access the authenticator.
 *
 * @package activedirectory
 */
class SAMLLoginForm extends LoginForm
{
    /**
     * This field is used in the "You are logged in as %s" message
     * @var string
     */
    public $loggedInAsField = 'FirstName';

    /**
     * @var string
     */
    protected $authenticator_class = SAMLAuthenticator::class;

    /**
     * The name of this login form, to display in the frontend
     * Replaces Authenticator::get_name()
     *
     * @return string
     */
    public function getAuthenticatorName()
    {
        return _t(__CLASS__ . '.AUTHENTICATORNAME', 'SAML');
    }

    /**
     * Constructor
     *
     * @param RequestHandler $controller
     * @param string $authenticatorClass
     * @param string $name method on the $controller
     */
    public function __construct(RequestHandler $controller, $authenticatorClass, $name)
    {
        $backURL = $this->getSession()->get('BackURL');

        if (!empty($this->getRequest()->requestVar('BackURL'))) {
            $backURL = $this->getRequest()->requestVar('BackURL');
        }
        if ($this->shouldShowLogoutFields()) {
            $fields = FieldList::create([
                HiddenField::create('AuthenticationMethod', null, $this->authenticator_class, $this)
            ]);
            $actions = FieldList::create([
                FormAction::create(
                    'logout',
                    _t('SilverStripe\\Security\\Member.BUTTONLOGINOTHER', 'Log in as someone else')
                )
            ]);
        } else {
            $fields = $this->getFormFields();
            $actions = $this->getFormActions();
        }

        if ($backURL) {
            $fields->push(HiddenField::create('BackURL', 'BackURL', $backURL));
        }

        $this->setFormMethod('POST', true);

        parent::__construct($controller, $name, $fields, $actions);
    }

    protected function getFormFields()
    {
        return FieldList::create([
            HiddenField::create('AuthenticationMethod', null, $this->authenticator_class, $this)
        ]);
    }

    protected function getFormActions()
    {
        return FieldList::create([
            FormAction::create('dologin', _t('SilverStripe\\Security\\Member.BUTTONLOGIN', 'Log in'))
        ]);
    }

    /**
     * @return bool
     */
    protected function shouldShowLogoutFields()
    {
        if (!Security::getCurrentUser()) {
            return false;
        }
        return true;
    }
}
