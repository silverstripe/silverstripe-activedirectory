<?php
/**
 * LDAPService provides operations expressed in terms of the SilverStripe domain. All other modules should access
 * LDAP through this class.
 *
 * This class builds on top of LDAPGateway's detailed code by adding:
 * - caching
 * - data aggregation and restructuring from multiple lower-level calls
 * - error handling
 *
 * LDAPService relies on Zend LDAP module's data structures for some parameters and some return values.
 */
class LDAPService extends Object implements Flushable {

	private static $dependencies = array(
		'gateway' => '%$LDAPGateway'
	);

	/**
	 * If configured, only objects within these locations will be exposed to this service.
	 * @config
	 */
	private static $search_locations = array();

	private static $_cache_nested_groups = array();

	/**
	 * Get the cache objecgt used for LDAP results. Note that the default lifetime set here
	 * is 8 hours, but you can change that by calling SS_Cache::set_lifetime('ldap', <lifetime in seconds>)
	 *
	 * @return Zend_Cache_Frontend
	 */
	public static function get_cache() {
		return SS_Cache::factory('ldap', 'Output', array(
			'automatic_serialization' => true,
			'lifetime' => 28800
		));
	}

	/**
	 * Flushes out the LDAP results cache when flush=1 is called.
	 */
	public static function flush() {
		$cache = self::get_cache();
		$cache->clean(Zend_Cache::CLEANING_MODE_ALL);
	}

	/**
	 * @var LDAPGateway
	 */
	public $gateway;

	/**
	 * Setter for getter property. Useful for overriding the gateway with a fake for testing.
	 * @var LDAPGateway
	 */
	public function setGateway($gateway) {
		$this->gateway = $gateway;
	}

	/**
	 * Authenticate the given username and password with LDAP.
	 *
	 * @param string $username
	 * @param string $password
	 */
	public function authenticate($username, $password) {
		$result = $this->gateway->authenticate($username, $password);
		$messages = $result->getMessages();

		// all messages beyond the first one are for debugging and
		// not suitable to display to the user.
		foreach($messages as $i => $message) {
			if($i > 0) {
				SS_Log::log(str_replace("\n", "\n  ", $message), SS_Log::DEBUG);
			}
		}

		return array(
			'success' => $result->getCode() === 1,
			'identity' => $result->getIdentity(),
			'message' => $messages[0] // first message is user readable, suitable for showing back to the login form
		);
	}

	/**
	 * Return all AD groups in configured search locations, including all nested groups.
	 * Uses search_locations if defined, otherwise falls back to NULL, which tells LDAPGateway
	 * to use the default baseDn defined in the connection.
	 *
	 * @param boolean $cached Cache the results from AD, so that subsequent calls are faster. Enabled by default.
	 * @param array $attributes List of specific AD attributes to return. Empty array means return everything.
	 * @return array
	 */
	public function getGroups($cached = true, $attributes = array()) {
		$searchLocations = $this->config()->search_locations ?: array(null);
		$cache = self::get_cache();
		$results = $cache->load('groups' . md5(implode('', array_merge($searchLocations, $attributes))));

		if(!$results || !$cached) {
			$results = array();
			foreach($searchLocations as $searchLocation) {
				$records = $this->gateway->getGroups($searchLocation, Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes);
				foreach($records as $record) {
					$results[$record['dn']] = $record;
				}
			}

			$cache->save($results);
		}

		return $results;
	}

	/**
	 * Return all nested AD groups underneath a specific DN. Note that these get cached in-memory
	 * per-request for performance to avoid re-querying for the same results.
	 *
	 * Note the recursion is done by the AD server, and the groups are provided as a flat list
	 * (even though they are really a nested structure).
	 *
	 * @param string $dn
	 * @param array $attributes List of specific AD attributes to return. Empty array means return everything.
	 * @return array
	 */
	public function getNestedGroups($dn, $attributes = array()) {
		if(isset(self::$_cache_nested_groups[$dn])) {
			return self::$_cache_nested_groups[$dn];
		}

		$searchLocations = $this->config()->search_locations ?: array(null);
		$results = array();
		foreach($searchLocations as $searchLocation) {
			$records = $this->gateway->getNestedGroups($dn, $searchLocation, Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes);
			foreach($records as $record) {
				$results[$record['dn']] = $record;
			}
		}

		self::$_cache_nested_groups[$dn] = $results;
		return $results;
	}

	/**
	 * Get a particular AD group's data given a GUID.
	 *
	 * @param string $guid
	 * @param array $attributes List of specific AD attributes to return. Empty array means return everything.
	 * @return array
	 */
	public function getGroupByGUID($guid, $attributes = array()) {
		$searchLocations = $this->config()->search_locations ?: array(null);
		foreach($searchLocations as $searchLocation) {
			$records = $this->gateway->getGroupByGUID($guid, $searchLocation, Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes);
			if($records) return $records[0];
		}
	}

	/**
	 * Return all AD users in configured search locations, including all users in nested groups.
	 * Uses search_locations if defined, otherwise falls back to NULL, which tells LDAPGateway
	 * to use the default baseDn defined in the connection.
	 *
	 * @param array $attributes List of specific AD attributes to return. Empty array means return everything.
	 * @return array
	 */
	public function getUsers($attributes = array()) {
		$searchLocations = $this->config()->search_locations ?: array(null);
		$results = array();

		foreach($searchLocations as $searchLocation) {
			$records = $this->gateway->getUsers($searchLocation, Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes);
			foreach($records as $record) {
				$results[$record['objectguid']] = $record;
			}
		}

		return $results;
	}

	/**
	 * Get a specific AD user's data given a GUID.
	 *
	 * @param string $guid
	 * @param array $attributes List of specific AD attributes to return. Empty array means return everything.
	 * @return array
	 */
	public function getUserByGUID($guid, $attributes = array()) {
		$searchLocations = $this->config()->search_locations ?: array(null);
		foreach($searchLocations as $searchLocation) {
			$records = $this->gateway->getUserByGUID($guid, $searchLocation, Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes);
			if($records) return $records[0];
		}
	}

	/**
	 * Get a specific user's data given a username.
	 *
	 * @param string $username
	 * @param array $attributes List of specific AD attributes to return. Empty array means return everything.
	 * @return array
	 */
	public function getUserByUsername($username, $attributes = array()) {
		$searchLocations = $this->config()->search_locations ?: array(null);
		foreach($searchLocations as $searchLocation) {
			$records = $this->gateway->getUserByUsername($username, $searchLocation, Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes);
			if($records) return $records[0];
		}
	}

	/**
	 * Update the current Member record with data from LDAP.
	 *
	 * Constraints:
	 * - Member *must* be in the database before calling this as it will need the ID to be mapped to a {@link Group}.
	 * - GUID of the member must have already been set, for integrity reasons we don't allow it to change here.
	 *
	 * @param Member
	 * @param array|null $data If passed, this is pre-existing AD attribute data to update the Member with.
	 *			If not given, the data will be looked up by the user's GUID.
	 */
	public function updateMemberFromLDAP($member, $data = null) {
		if (!$member->GUID) {
			SS_Log::log(sprintf('Cannot update Member ID %s, GUID not set', $member->ID), SS_Log::WARN);
			return false;
		}

		if(!$data) {
			$data = $this->getUserByGUID($member->GUID);
			if(!$data) {
				SS_Log::log(sprintf('Could not retrieve data for user. GUID: %s', $member->GUID), SS_Log::WARN);
				return false;
			}
		}

		// if we already imported this user, and they haven't been modified since the last time, skip.
		if(strtotime($data['whenchanged']) <= strtotime($member->LastEditedLDAP)) {
			return false;
		}

		$member->IsExpired = ($data['useraccountcontrol'] & 2) == 2;
		$member->LastEditedLDAP = $data['whenchanged'];
		$member->LastSynced = SS_Datetime::now();
		$member->IsImportedFromLDAP = true;

		foreach($member->config()->ldap_field_mappings as $attribute => $field) {
			if(!isset($data[$attribute])) {
				SS_Log::log(sprintf(
					'Attribute %s configured in Member.ldap_field_mappings, but no available attribute in AD data (GUID: %s, Member ID: %s)',
					$attribute,
					$data['objectguid'],
					$member->ID
				), SS_Log::WARN);

				continue;
			}

			if($attribute == 'thumbnailphoto') {
				$imageClass = $member->getRelationClass($field);
				if($imageClass!=='Image' && !is_subclass_of($imageClass, 'Image')) {
					SS_Log::log(sprintf(
						'Member field %s configured for thumbnailphoto AD attribute, but it isn\'t a valid relation to an Image class',
						$field
					), SS_Log::WARN);

					continue;
				}

				$filename = sprintf('thumbnailphoto-%s.jpg', $data['samaccountname']);
				$path = ASSETS_DIR . '/' . $member->config()->ldap_thumbnail_path;
				$absPath = BASE_PATH . '/' . $path;
				if(!file_exists($absPath)) {
					Filesystem::makeFolder($absPath);
				}

				// remove existing record if it exists
				$existingObj = $member->getComponent($field);
				if($existingObj && $existingObj->exists()) {
					$existingObj->delete();
				}

				// The image data is provided in raw binary.
				file_put_contents($absPath . '/' . $filename, $data[$attribute]);
				$record = new $imageClass();
				$record->Name = $filename;
				$record->Filename = $path . '/' . $filename;
				$record->write();

				$relationField = $field . 'ID';
				$member->{$relationField} = $record->ID;
			} else {
				$member->$field = $data[$attribute];
			}
		}

		// this is to keep track of which groups the user gets mapped to
		// and we'll use that later to remove them from any groups that they're no longer mapped to
		$mappedGroupIDs = array();

		// ensure the user is in any mapped groups
		if(isset($data['memberof'])) {
			$ldapGroups = is_array($data['memberof']) ? $data['memberof'] : array($data['memberof']);
			foreach($ldapGroups as $groupDN) {
				foreach(LDAPGroupMapping::get() as $mapping) {
					if(!$mapping->DN) {
						SS_Log::log(
							sprintf(
								'LDAPGroupMapping ID %s is missing DN field. Skipping',
								$mapping->ID
							),
							SS_Log::WARN
						);
						continue;
					}

					// the user is a direct member of group with a mapping, add them to the SS group.
					if($mapping->DN == $groupDN) {
						$mapping->Group()->Members()->add($member, array(
							'IsImportedFromLDAP' => '1'
						));
						$mappedGroupIDs[] = $mapping->GroupID;
					}

					// the user *might* be a member of a nested group provided the scope of the mapping
					// is to include the entire subtree. Check all those mappings and find the LDAP child groups
					// to see if they are a member of one of those. If they are, add them to the SS group
					if($mapping->Scope == 'Subtree') {
						$childGroups = $this->getNestedGroups($mapping->DN, array('dn'));
						if(!$childGroups) continue;

						foreach($childGroups as $childGroupDN => $childGroupRecord) {
							if($childGroupDN == $groupDN) {
								$mapping->Group()->Members()->add($member, array(
									'IsImportedFromLDAP' => '1'
								));
								$mappedGroupIDs[] = $mapping->GroupID;
							}
						}
					}
				}
			}
		}

		// remove the user from any previously mapped groups, where the mapping has since been removed
		$groupRecords = DB::query(sprintf('SELECT "GroupID" FROM "Group_Members" WHERE "IsImportedFromLDAP" = 1 AND "MemberID" = %s', $member->ID));
		foreach($groupRecords as $groupRecord) {
			if(!in_array($groupRecord['GroupID'], $mappedGroupIDs)) {
				Group::get()->byId($groupRecord['GroupID'])->Members()->remove($member);
			}
		}

		// This will throw an exception if there are two distinct GUIDs with the same email address.
		// We are happy with a raw 500 here at this stage.
		$member->write();

		return true;
	}

}
