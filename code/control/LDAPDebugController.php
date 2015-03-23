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

	public function SearchLocations() {
		$locations = Config::inst()->get('LDAPService', 'search_locations');
		$list = new ArrayList();
		if($locations) {
			foreach($locations as $location) {
				$list->push(new ArrayData(array(
					'Value' => $location
				)));
			}
		} else {
			$list->push($this->Options()->find('Name', 'baseDn'));
		}

		return $list;
	}

	public function Groups() {
		$groups = $this->ldapService->getGroups(false);
		$list = new ArrayList();
		foreach($groups as $record) {
			$list->push(new ArrayData(array(
				'DN' => $record['dn']
			)));
		}
		return $list;
	}

	public function Users() {
		return count($this->ldapService->getUsers());
	}

}
