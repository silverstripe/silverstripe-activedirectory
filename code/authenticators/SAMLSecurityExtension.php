<?php

class SAMLSecurityExtension extends Extension {

	/**
	 * Will redirect the user directly to the ADFS if:
	 * 1) the 'SAMLAuthenticator' is the default authenticator
	 * 2) there isn't a GET param showloginform set to 1
	 *
	 * @return void
	 */
	public function onBeforeSecurityLogin() {

		if(Authenticator::get_default_authenticator() != 'SAMLAuthenticator') {
			return;
		}
		// if there is a backdoor, don't redirect
		if(isset($_GET['showloginform']) && $_GET['showloginform'] == 1) {
			return;
		}

		$authenticator = Injector::inst()->create('SAMLAuthenticator');
		$authenticator->authenticate(array());
	}
}