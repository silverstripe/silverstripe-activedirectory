<?php
/**
 * Class LDAPAuthenticator
 *
 * Authenticate a user against LDAP, without the single sign-on component.
 *
 * See SAMLAuthenticator for further information.
 */
class LDAPAuthenticator extends Authenticator {

	/**
	 * @var string
	 */
	private $name = 'LDAP';

	/**
	 * @return string
	 */
	public static function get_name() {
		return Config::inst()->get('LDAPAuthenticator', 'name');
	}

	/**
	 * @param Controller $controller
	 * @return LDAPLoginForm
	 */
	public static function get_login_form(Controller $controller) {
		return new LDAPLoginForm($controller, 'LoginForm');
	}

	/**
	 * Performs the login, but will also create and sync the Member record on-the-fly, if not found.
	 *
	 * @param array $data
	 * @param Form $form
	 * @return bool|Member|void
	 * @throws SS_HTTPResponse_Exception
	 */
	public static function authenticate($data, Form $form = null) {
		$service = Injector::inst()->get('LDAPService');
		$result = $service->authenticate($data['Username'], $data['Password']);

		$success = $result['success'] === true;
		if(!$success) {
			if($form) $form->sessionMessage($result['message'], 'bad');
			return;
		}

		$data = $service->getUserByUsername($result['identity']);
		if(!$data) {
			if($form) {
				$form->sessionMessage(
					_t('LDAPAuthenticator.PROBLEMFINDINGDATA', 'There was a problem retrieving your user data'),
					'bad'
				);
			}
			return;
		}

		// LDAPMemberExtension::memberLoggedIn() will update any other AD attributes mapped to Member fields
		$member = Member::get()->filter('GUID', $data['objectguid'])->limit(1)->first();
		if(!($member && $member->exists())) {
			$member = new Member();
			$member->GUID = $data['objectguid'];
			$member->write();
		}

		Session::clear('BackURL');

		return $member;
	}

}
