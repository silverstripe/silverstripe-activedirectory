<?php
/**
 * Class SAMLSecurityExtension
 *
 * Extensions to the {@link Security} controller to support {@link SAMLAuthenticator}
 */
class SAMLSecurityExtension extends Extension
{
    /**
     * Will redirect the user directly to the IdP login endpoint if:
     *
     * 1) the 'SAMLAuthenticator' is the default authenticator
     * 2) there isn't a GET param showloginform set to 1
     * 3) the member is not currently logged in
     * 4) there are no form messages (errors or notices)
     *
     * @return void
     */
    public function onBeforeSecurityLogin()
    {
        if (Authenticator::get_default_authenticator() != 'SAMLAuthenticator') {
            return;
        }

        // by going to the URL Security/login?showloginform=1 we bypass the auto sign on
        if ($this->owner->request->getVar('showloginform') == 1) {
            return;
        }

        // if member is already logged in, don't auto-sign-on, this is most likely because
        // of unsufficient permissions.
        $member = Member::currentUser();
        if ($member && $member->exists()) {
            return;
        }

        // if there are form messages, don't auto-sign-on, this is most likely because of
        // login errors / failures or other notices.
        if (Session::get('FormInfo')) {
            // since FormInfo can be a "nulled" array, we have to check
            foreach (Session::get('FormInfo') as $form => $info) {
                foreach ($info as $name => $value) {
                    if ($value !== null) {
                        return;
                    }
                }
            }
        }

        $backURL = Session::get('BackURL');
        if ($this->owner->request->getVar('BackURL')) {
            $backURL = $this->owner->request->getVar('BackURL');
        }

        $authenticator = Injector::inst()->create('SAMLAuthenticator');
        $authenticator->authenticate(["BackURL" => $backURL]);
    }
}
