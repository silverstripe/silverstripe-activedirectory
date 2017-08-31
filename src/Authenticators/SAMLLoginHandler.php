<?php

namespace SilverStripe\ActiveDirectory\Authenticators;

use SilverStripe\Security\MemberAuthenticator\LoginHandler;

class SAMLLoginHandler extends LoginHandler
{
    public function loginForm()
    {
        return SAMLLoginForm::create(
            $this,
            get_class($this->authenticator),
            'LoginForm'
        );
    }
}
