<?php
/**
 * Class LDAPGateway
 *
 * Works within the LDAP domain model to provide basic operations.
 * These are exclusively used in LDAPService for constructing more complex operations.
 */
class LDAPGateway extends Object {

	/**
	 * @var array
	 * @config
	 */
	private static $options = array();

	/**
	 * @var Zend\Ldap\Ldap
	 */
	private $ldap;

	public function __construct() {
		$this->ldap = new Zend\Ldap\Ldap($this->config()->options);
	}

	/**
	 * Query the LDAP directory with the given filter.
	 *
	 * @param string $filter The string to filter by, e.g. (objectClass=user)
	 * @param null|string $baseDn The DN to search from. Default is the baseDn option in the connection if not given
	 * @param int $scope The scope to perform the search. Zend_Ldap::SEARCH_SCOPE_ONE, Zend_LDAP::SEARCH_SCOPE_BASE. Default is Zend_Ldap::SEARCH_SCOPE_SUB
	 * @param array $attributes Restrict to specific AD attributes. An empty array will return all attributes
	 * @param string $sort Sort results by this attribute if given
	 * @return array
	 */
	protected function search($filter, $baseDn = null, $scope = Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes = array(), $sort = '') {
		$records = $this->ldap->search($filter, $baseDn, $scope, $attributes, $sort);

		$results = array();
		foreach($records as $record) {
			foreach($record as $attribute => $value) {
				// if the value is an array with a single value, e.g. 'samaccountname' => array(0 => 'myusername')
				// then make sure it's just set in the results as 'samaccountname' => 'myusername' so that it
				// can be used directly by ArrayData
				if(is_array($value) && count($value) == 1) {
					$value = $value[0];
				}

				// ObjectGUID and ObjectSID attributes are in binary, we need to convert those to strings
				if($attribute == 'objectguid') {
					$value = LDAPUtil::bin_to_str_guid($value);
				}
				if($attribute == 'objectsid') {
					$value = LDAPUtil::bin_to_str_sid($value);
				}

				$record[$attribute] = $value;
			}

			$results[] = $record;
		}

		return $results;
	}

	/**
	 * Authenticate the given username and password with LDAP.
	 *
	 * @param string $username
	 * @param string $password
	 * @return \Zend\Authentication\Result
	 */
	public function authenticate($username, $password) {
		$auth = new Zend\Authentication\AuthenticationService();
		$adapter = new Zend\Authentication\Adapter\Ldap(array($this->config()->options), $username, $password);
		return $auth->authenticate($adapter);
	}

	/**
	 * Query for LDAP nodes (organizational units, containers, and domains).
	 *
	 * @param null|string $baseDn The DN to search from. Default is the baseDn option in the connection if not given
	 * @param int $scope The scope to perform the search. Zend_Ldap::SEARCH_SCOPE_ONE, Zend_LDAP::SEARCH_SCOPE_BASE. Default is Zend_Ldap::SEARCH_SCOPE_SUB
	 * @param array $attributes Restrict to specific AD attributes. An empty array will return all attributes
	 * @param string $sort Sort results by this attribute if given
	 * @return array
	 */
	public function getNodes($baseDn = null, $scope = Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes = array(), $sort = '') {
		return $this->search('(|(objectClass=organizationalUnit)(objectClass=container)(objectClass=domain))', $baseDn, $scope, $attributes, $sort);
	}

	/**
	 * Query for LDAP groups.
	 *
	 * @param null|string $baseDn The DN to search from. Default is the baseDn option in the connection if not given
	 * @param int $scope The scope to perform the search. Zend_Ldap::SEARCH_SCOPE_ONE, Zend_LDAP::SEARCH_SCOPE_BASE. Default is Zend_Ldap::SEARCH_SCOPE_SUB
	 * @param array $attributes Restrict to specific AD attributes. An empty array will return all attributes
	 * @param string $sort Sort results by this attribute if given
	 * @return array
	 */
	public function getGroups($baseDn = null, $scope = Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes = array(), $sort = '') {
		return $this->search('(objectClass=group)', $baseDn, $scope, $attributes, $sort);
	}

	/**
	 * Return all nested AD groups underneath a specific DN
	 *
	 * @param string $dn
	 * @param null|string $baseDn The DN to search from. Default is the baseDn option in the connection if not given
	 * @param int $scope The scope to perform the search. Zend_Ldap::SEARCH_SCOPE_ONE, Zend_LDAP::SEARCH_SCOPE_BASE. Default is Zend_Ldap::SEARCH_SCOPE_SUB
	 * @param array $attributes Restrict to specific AD attributes. An empty array will return all attributes
	 * @return array
	 */
	public function getNestedGroups($dn, $baseDn = null, $scope = Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes = array()) {
		return $this->search(
			sprintf('(&(objectClass=group)(memberOf:1.2.840.113556.1.4.1941:=%s))', $dn),
			$baseDn,
			$scope,
			$attributes
		);
	}

	/**
	 * Return a particular LDAP group by objectGUID value.
	 *
	 * @param string $guid
	 * @param null|string $baseDn The DN to search from. Default is the baseDn option in the connection if not given
	 * @param int $scope The scope to perform the search. Zend_Ldap::SEARCH_SCOPE_ONE, Zend_LDAP::SEARCH_SCOPE_BASE. Default is Zend_Ldap::SEARCH_SCOPE_SUB
	 * @param array $attributes Restrict to specific AD attributes. An empty array will return all attributes
	 * @return array
	 */
	public function getGroupByGUID($guid, $baseDn = null, $scope = Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes = array()) {
		return $this->search(
			sprintf('(&(objectClass=group)(objectGUID=%s))', LDAPUtil::str_to_hex_guid($guid, true)),
			$baseDn,
			$scope,
			$attributes
		);
	}

	/**
	 * Query for LDAP users, but don't include built-in user accounts.
	 *
	 * @param null|string $baseDn The DN to search from. Default is the baseDn option in the connection if not given
	 * @param int $scope The scope to perform the search. Zend_Ldap::SEARCH_SCOPE_ONE, Zend_LDAP::SEARCH_SCOPE_BASE. Default is Zend_Ldap::SEARCH_SCOPE_SUB
	 * @param array $attributes Restrict to specific AD attributes. An empty array will return all attributes
	 * @param string $sort Sort results by this attribute if given
	 * @return array
	 */
	public function getUsers($baseDn = null, $scope = Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes = array(), $sort = '') {
		return $this->search(
			'(&(objectClass=user)(!(objectClass=computer))(!(samaccountname=Guest))(!(samaccountname=Administrator))(!(samaccountname=krbtgt)))',
			$baseDn,
			$scope,
			$attributes,
			$sort
		);
	}

	/**
	 * Return a particular LDAP user by objectGUID value.
	 *
	 * @param string $guid
	 * @return array
	 */
	public function getUserByGUID($guid, $baseDn = null, $scope = Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes = array()) {
		return $this->search(
			sprintf('(&(objectClass=user)(objectGUID=%s))', LDAPUtil::str_to_hex_guid($guid, true)),
			$baseDn,
			$scope,
			$attributes
		);
	}

	/**
	 * Get a specific user's data from LDAP
	 *
	 * @param string $username
	 * @param null|string $baseDn The DN to search from. Default is the baseDn option in the connection if not given
	 * @param int $scope The scope to perform the search. Zend_Ldap::SEARCH_SCOPE_ONE, Zend_LDAP::SEARCH_SCOPE_BASE. Default is Zend_Ldap::SEARCH_SCOPE_SUB
	 * @param array $attributes Restrict to specific AD attributes. An empty array will return all attributes
	 * @return array
	 * @throws Exception
	 */
	public function getUserByUsername($username, $baseDn = null, $scope = Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes = array()) {
		$options = $this->config()->options;
		$option = isset($options['accountCanonicalForm']) ? $options['accountCanonicalForm'] : null;

		// will translate the username to username@foo.bar, username or foo\user depending on the
		// $options['accountCanonicalForm']
		$username = $this->ldap->getCanonicalAccountName($username);
		switch($option) {
			case Zend\Ldap\Ldap::ACCTNAME_FORM_USERNAME: // traditional style usernames, e.g. alice
				$filter = sprintf('(&(objectClass=user)(samaccountname=%s))', Zend\Ldap\Filter\AbstractFilter::escapeValue($username));
				break;
			case Zend\Ldap\Ldap::ACCTNAME_FORM_BACKSLASH: // backslash style usernames, e.g. FOO\alice
				// @todo Not supported yet!
				throw new Exception('Backslash style not supported in LDAPGateway::getUserByUsername()!');
				break;
			case Zend\Ldap\Ldap::ACCTNAME_FORM_PRINCIPAL: // principal style usernames, e.g. alice@foo.com
				$filter = sprintf('(&(objectClass=user)(userprincipalname=%s))', Zend\Ldap\Filter\AbstractFilter::escapeValue($username));
				break;
			default: // default to principal style
				$filter = sprintf('(&(objectClass=user)(userprincipalname=%s))', Zend\Ldap\Filter\AbstractFilter::escapeValue($username));
		}
		return $this->search($filter, $baseDn, $scope, $attributes);
	}

	/**
	 * Updates attributes for an object. For this work you might need that LDAP connection
	 * is bind:ed with a user with enough permissions to change attributes and that the LDAP
	 * connection is using SSL/TLS. It depends on the server setup.
	 *
	 * If there are some errors, the underlying LDAP library should throw an Exception
	 *
	 * @param string $username - the users distinguishedname
	 * @param array $attributes - an array attributename => value to change
	 * @throws \Zend\Ldap\Exception\LdapException
	 */
	public function changeObjectAttribute($username, array $attributes) {
		$this->ldap->update($username, $attributes);
	}

}
