<?php

namespace SilverStripe\ActiveDirectory\Authenticators;

use SilverStripe\Control\Session;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\FormAction;
use SilverStripe\Security\LoginForm;
use SilverStripe\Security\Member;
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
    protected $authenticator_class = 'SilverStripe\\ActiveDirectory\\Authenticators\\SAMLAuthenticator';

    /**
     * Constructor
     *
     * @param Controller $controller
     * @param string $name method on the $controller
     * @param FieldList $fields
     * @param FieldList $actions
     * @param bool $checkCurrentUser - show logout button if logged in
     */
    public function __construct($controller, $name, $fields = null, $actions = null, $checkCurrentUser = true)
    {
        $backURL = Session::get('BackURL');

        if (isset($_REQUEST['BackURL'])) {
            $backURL = $_REQUEST['BackURL'];
        }

        if ($checkCurrentUser && $this->shouldShowLogoutFields()) {
            $fields = FieldList::create([
                HiddenField::create('AuthenticationMethod', null, $this->authenticator_class, $this)
            ]);
            $actions = FieldList::create([
                FormAction::create('logout', _t('Member.BUTTONLOGINOTHER', 'Log in as someone else'))
            ]);
        } else {
            if (!$fields) {
                $fields = FieldList::create([
                    HiddenField::create('AuthenticationMethod', null, $this->authenticator_class, $this)
                ]);
            }
            if (!$actions) {
                $actions = FieldList::create([
                    FormAction::create('dologin', _t('Member.BUTTONLOGIN', 'Log in'))
                ]);
            }
        }

        if ($backURL) {
            $fields->push(HiddenField::create('BackURL', 'BackURL', $backURL));
        }

        $this->setFormMethod('POST', true);

        parent::__construct($controller, $name, $fields, $actions);
    }


    /**
     *
     *
     * @return bool
     */
    protected function shouldShowLogoutFields()
    {
        if (!Member::currentUser()) {
            return false;
        }
        if (!Member::logged_in_session_exists()) {
            return false;
        }
        return true;
    }

    /**
     * Get message from session
     */
    protected function getMessageFromSession()
    {
        // The "MemberLoginForm.force_message session" is set in Security#permissionFailure()
        // and displays messages like "You don't have access to this page"
        // if force isn't set, it will just display "You're logged in as {name}"
        if (($member = Member::currentUser()) && !Session::get('MemberLoginForm.force_message')) {
            $this->message = _t(
                'Member.LOGGEDINAS',
                "You're logged in as {name}.",
                ['name' => $member->{$this->loggedInAsField}]
            );
        }
        Session::set('MemberLoginForm.force_message', false);
        parent::getMessageFromSession();
        return $this->message;
    }


    /**
     * Login form handler method
     *
     * This method is called when the user clicks on "Log in"
     *
     * @param array $data Submitted data
     */
    public function dologin($data)
    {
        call_user_func_array([$this->authenticator_class, 'authenticate'], [$data, $this]);
    }


    /**
     * Log out form handler method
     *
     * This method is called when the user clicks on "logout" on the form
     * created when the parameter <i>$checkCurrentUser</i> of the
     * {@link __construct constructor} was set to TRUE and the user was
     * currently logged in.
     */
    public function logout()
    {
        $s = new Security();
        $s->logout(false);
    }
}
