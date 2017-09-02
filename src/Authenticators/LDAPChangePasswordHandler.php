<?php

namespace SilverStripe\ActiveDirectory\Authenticators;

use SilverStripe\ActiveDirectory\Forms\LDAPChangePasswordForm;
use SilverStripe\Security\MemberAuthenticator\ChangePasswordHandler;

class LDAPChangePasswordHandler extends ChangePasswordHandler
{
    /**
     * Factory method for the lost password form
     *
     * @return LDAPChangePasswordForm Returns the lost password form
     */
    public function changePasswordForm()
    {
        return LDAPChangePasswordForm::create($this, 'ChangePasswordForm');
    }
}
