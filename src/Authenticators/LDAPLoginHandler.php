<?php

namespace SilverStripe\ActiveDirectory\Authenticators;

use SilverStripe\ActiveDirectory\Forms\LDAPLoginForm;
use SilverStripe\Security\MemberAuthenticator\LoginHandler;

class LDAPLoginHandler extends LoginHandler
{
    /**
     * @var array
     * @config
     */
    private static $allowed_actions = [
        'LoginForm',
    ];

    public function loginForm()
    {
        return LDAPLoginForm::create(
            $this,
            get_class($this->authenticator),
            'LoginForm'
        );
    }
}
