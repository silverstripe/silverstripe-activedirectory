<?php

namespace SilverStripe\ActiveDirectory\Authenticators;

use SilverStripe\ActiveDirectory\Services\LDAPService;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\Member;
use SilverStripe\Security\MemberAuthenticator\LoginHandler;

class LDAPMemberLoginHandler extends LoginHandler
{
    /**
     * @var string
     */
    protected $authenticator_class = LDAPAuthenticator::class;
}
