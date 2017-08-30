<?php

namespace SilverStripe\ActiveDirectory\Authenticators;

use SilverStripe\Control\Session;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\Authenticator;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * Class SAMLSecurityExtension
 *
 * Extensions to the {@link Security} controller to support {@link SAMLAuthenticator}
 *
 * @package activedirectory
 */
class SAMLSecurityExtension extends Extension
{
    /**
     * Will redirect the user directly to the IdP login endpoint if:
     *
     * 1) There isn't a GET param showloginform set to 1
     * 2) the member is not currently logged in
     * 3) there are no form messages (errors or notices)
     *
     * @return void
     */
    public function onBeforeSecurityLogin()
    {
        // by going to the URL Security/login?showloginform=1 we bypass the auto sign on
        if ($this->owner->request->getVar('showloginform') == 1) {
            return;
        }

        // if member is already logged in, don't auto-sign-on, this is most likely because
        // of insufficient permissions.
        $member = Security::getCurrentUser();
        if ($member && $member->exists()) {
            return;
        }
        $session = $this->owner->getRequest()->getSession();
        // if there are form messages, don't auto-sign-on, this is most likely because of
        // login errors / failures or other notices.
        if ($session->get('FormInfo')) {
            // since FormInfo can be a "nulled" array, we have to check
            foreach ($session->get('FormInfo') as $form => $info) {
                foreach ($info as $name => $value) {
                    if ($value !== null) {
                        return;
                    }
                }
            }
        }

        $backURL = $session->get('BackURL');
        if ($this->owner->request->getVar('BackURL')) {
            $backURL = $this->owner->request->getVar('BackURL');
        }

        $this->owner->getRequest()->getSession()->set('BackURL', $backURL);
    }
}
