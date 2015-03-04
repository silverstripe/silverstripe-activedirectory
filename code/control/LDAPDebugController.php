<?php
/**
 * This controller will ping AD with a simple operation to see if the connection is working.
 */
class LDAPDebugController extends Controller {

	private static $dependencies = array(
		'ldapService' => '%$LDAPService'
	);

	public $ldapService;

	public function init() {
		parent::init();

		if(!Permission::check('ADMIN')) {
			Security::permissionFailure();
		}
	}

	public function Options() {
		$list = new ArrayList();
		foreach(Config::inst()->get('LDAPGateway', 'options') as $field => $value) {
			$list->push(new ArrayData(array(
				'Name' => $field,
				'Value' => $value
			)));
		}
		return $list;
	}

	public function Groups() {
		$groups = $this->ldapService->getGroups();
		$list = new ArrayList();
		foreach($groups as $dn) {
			$list->push(new ArrayData(array(
				'DN' => $dn
			)));
		}
		return $list;
	}

}
