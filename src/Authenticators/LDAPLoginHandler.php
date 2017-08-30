<?php

namespace SilverStripe\ActiveDirectory\Authenticators;

use SilverStripe\Security\MemberAuthenticator\LoginHandler;

class LDAPLoginHandler extends LoginHandler
{
    public function loginForm()
    {
        return LDAPLoginForm::create(
            $this,
            get_class($this->authenticator),
            'LDAPLoginForm'
        );
    }
}
