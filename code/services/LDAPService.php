<?php
/**
 * Class LDAPService
 *
 * Provides LDAP operations expressed in terms of the SilverStripe domain.
 * All other modules should access LDAP through this class.
 *
 * This class builds on top of LDAPGateway's detailed code by adding:
 * - caching
 * - data aggregation and restructuring from multiple lower-level calls
 * - error handling
 *
 * LDAPService relies on Zend LDAP module's data structures for some parameters and some return values.
 */
class LDAPService extends SS_Object implements Flushable
{
    /**
     * @var array
     */
    private static $dependencies = [
        'gateway' => '%$LDAPGateway'
    ];

    /**
     * If configured, only user objects within these locations will be exposed to this service.
     *
     * @var array
     * @config
     */
    private static $users_search_locations = [];

    /**
     * If configured, only group objects within these locations will be exposed to this service.
     * @var array
     *
     * @config
     */
    private static $groups_search_locations = [];

    /**
     * Location to create new users in (distinguished name).
     * @var string
     *
     * @config
     */
    private static $new_users_dn;

    /**
     * Location to create new groups in (distinguished name).
     * @var string
     *
     * @config
     */
    private static $new_groups_dn;

    /**
     * @var array
     */
    private static $_cache_nested_groups = [];

    /**
     * If this is configured to a "Code" value of a {@link Group} in SilverStripe, the user will always
     * be added to this group's membership when imported, regardless of any sort of group mappings.
     *
     * @var string
     * @config
     */
    private static $default_group;

    /**
     * For samba4 directory, there is no way to enforce password history on password resets.
     * This only happens with changePassword (which requires the old password).
     * This works around it by making the old password up and setting it administratively.
     *
     * A cleaner fix would be to use the LDAP_SERVER_POLICY_HINTS_OID connection flag,
     * but it's not implemented in samba https://bugzilla.samba.org/show_bug.cgi?id=12020
     *
     * @var bool
     */
    private static $password_history_workaround = false;

    /**
     * Get the cache objecgt used for LDAP results. Note that the default lifetime set here
     * is 8 hours, but you can change that by calling SS_Cache::set_lifetime('ldap', <lifetime in seconds>)
     *
     * @return Zend_Cache_Frontend
     */
    public static function get_cache()
    {
        return SS_Cache::factory('ldap', 'Output', [
            'automatic_serialization' => true,
            'lifetime' => 28800
        ]);
    }

    /**
     * Flushes out the LDAP results cache when flush=1 is called.
     */
    public static function flush()
    {
        $cache = self::get_cache();
        $cache->clean(Zend_Cache::CLEANING_MODE_ALL);
    }

    /**
     * @var LDAPGateway
     */
    public $gateway;

    /**
     * Setter for gateway. Useful for overriding the gateway with a fake for testing.
     * @var LDAPGateway
     */
    public function setGateway($gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Checkes whether or not the service is enabled.
     *
     * @return bool
     */
    public function enabled()
    {
        $options = Config::inst()->get('LDAPGateway', 'options');
        return !empty($options);
    }

    /**
     * Authenticate the given username and password with LDAP.
     *
     * @param string $username
     * @param string $password
     *
     * @return array
     */
    public function authenticate($username, $password)
    {
        $result = $this->gateway->authenticate($username, $password);
        $messages = $result->getMessages();

        // all messages beyond the first one are for debugging and
        // not suitable to display to the user.
        foreach ($messages as $i => $message) {
            if ($i > 0) {
                SS_Log::log(str_replace("\n", "\n  ", $message), SS_Log::DEBUG);
            }
        }

        $message = $messages[0]; // first message is user readable, suitable for showing on login form

        // show better errors than the defaults for various status codes returned by LDAP
        if (!empty($messages[1]) && strpos($messages[1], 'NT_STATUS_ACCOUNT_LOCKED_OUT') !== false) {
            $message = _t(
                'LDAPService.ACCOUNTLOCKEDOUT',
                'Your account has been temporarily locked because of too many failed login attempts. ' .
                'Please try again later.'
            );
        }
        if (!empty($messages[1]) && strpos($messages[1], 'NT_STATUS_LOGON_FAILURE') !== false) {
            $message = _t(
                'LDAPService.INVALIDCREDENTIALS',
                'The provided details don\'t seem to be correct. Please try again.'
            );
        }

        return [
            'success' => $result->getCode() === 1,
            'identity' => $result->getIdentity(),
            'message' => $message
        ];
    }

    /**
     * Return all nodes (organizational units, containers, and domains) within the current base DN.
     *
     * @param boolean $cached Cache the results from AD, so that subsequent calls are faster. Enabled by default.
     * @param array $attributes List of specific AD attributes to return. Empty array means return everything.
     * @return array
     */
    public function getNodes($cached = true, $attributes = [])
    {
        $cache = self::get_cache();
        $results = $cache->load('nodes' . md5(implode('', $attributes)));

        if (!$results || !$cached) {
            $results = [];
            $records = $this->gateway->getNodes(null, Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes);
            foreach ($records as $record) {
                $results[$record['dn']] = $record;
            }

            $cache->save($results);
        }

        return $results;
    }

    /**
     * Return all AD groups in configured search locations, including all nested groups.
     * Uses groups_search_locations if defined, otherwise falls back to NULL, which tells LDAPGateway
     * to use the default baseDn defined in the connection.
     *
     * @param boolean $cached Cache the results from AD, so that subsequent calls are faster. Enabled by default.
     * @param array $attributes List of specific AD attributes to return. Empty array means return everything.
     * @param string $indexBy Attribute to use as an index.
     * @return array
     */
    public function getGroups($cached = true, $attributes = [], $indexBy = 'dn')
    {
        $searchLocations = $this->config()->groups_search_locations ?: [null];
        $cache = self::get_cache();
        $results = $cache->load('groups' . md5(implode('', array_merge($searchLocations, $attributes))));

        if (!$results || !$cached) {
            $results = [];
            foreach ($searchLocations as $searchLocation) {
                $records = $this->gateway->getGroups($searchLocation, Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes);
                if (!$records) {
                    continue;
                }

                foreach ($records as $record) {
                    $results[$record[$indexBy]] = $record;
                }
            }

            $cache->save($results);
        }

        return $results;
    }

    /**
     * Return all member groups (and members of those, recursively) underneath a specific group DN.
     * Note that these get cached in-memory per-request for performance to avoid re-querying for the same results.
     *
     * @param string $dn
     * @param array $attributes List of specific AD attributes to return. Empty array means return everything.
     * @return array
     */
    public function getNestedGroups($dn, $attributes = [])
    {
        if (isset(self::$_cache_nested_groups[$dn])) {
            return self::$_cache_nested_groups[$dn];
        }

        $searchLocations = $this->config()->groups_search_locations ?: [null];
        $results = [];
        foreach ($searchLocations as $searchLocation) {
            $records = $this->gateway->getNestedGroups($dn, $searchLocation, Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes);
            foreach ($records as $record) {
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
    public function getGroupByGUID($guid, $attributes = [])
    {
        $searchLocations = $this->config()->groups_search_locations ?: [null];
        foreach ($searchLocations as $searchLocation) {
            $records = $this->gateway->getGroupByGUID($guid, $searchLocation, Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes);
            if ($records) {
                return $records[0];
            }
        }
    }

    /**
     * Get a particular AD group's data given a DN.
     *
     * @param string $dn
     * @param array $attributes List of specific AD attributes to return. Empty array means return everything.
     * @return array
     */
    public function getGroupByDN($dn, $attributes = [])
    {
        $searchLocations = $this->config()->groups_search_locations ?: [null];
        foreach ($searchLocations as $searchLocation) {
            $records = $this->gateway->getGroupByDN($dn, $searchLocation, Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes);
            if ($records) {
                return $records[0];
            }
        }
    }

    /**
     * Return all AD users in configured search locations, including all users in nested groups.
     * Uses users_search_locations if defined, otherwise falls back to NULL, which tells LDAPGateway
     * to use the default baseDn defined in the connection.
     *
     * @param array $attributes List of specific AD attributes to return. Empty array means return everything.
     * @return array
     */
    public function getUsers($attributes = [])
    {
        $searchLocations = $this->config()->users_search_locations ?: [null];
        $results = [];

        foreach ($searchLocations as $searchLocation) {
            $records = $this->gateway->getUsers($searchLocation, Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes);
            if (!$records) {
                continue;
            }

            foreach ($records as $record) {
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
    public function getUserByGUID($guid, $attributes = [])
    {
        $searchLocations = $this->config()->users_search_locations ?: [null];
        foreach ($searchLocations as $searchLocation) {
            $records = $this->gateway->getUserByGUID($guid, $searchLocation, Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes);
            if ($records) {
                return $records[0];
            }
        }
    }

    /**
     * Get a specific AD user's data given a DN.
     *
     * @param string $dn
     * @param array $attributes List of specific AD attributes to return. Empty array means return everything.
     *
     * @return array
     */
    public function getUserByDN($dn, $attributes = [])
    {
        $searchLocations = $this->config()->users_search_locations ?: [null];
        foreach ($searchLocations as $searchLocation) {
            $records = $this->gateway->getUserByDN($dn, $searchLocation, Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes);
            if ($records) {
                return $records[0];
            }
        }
    }

    /**
     * Get a specific user's data given an email.
     *
     * @param string $email
     * @param array $attributes List of specific AD attributes to return. Empty array means return everything.
     * @return array
     */
    public function getUserByEmail($email, $attributes = [])
    {
        $searchLocations = $this->config()->users_search_locations ?: [null];
        foreach ($searchLocations as $searchLocation) {
            $records = $this->gateway->getUserByEmail($email, $searchLocation, Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes);
            if ($records) {
                return $records[0];
            }
        }
    }

    /**
     * Get a specific user's data given a username.
     *
     * @param string $username
     * @param array $attributes List of specific AD attributes to return. Empty array means return everything.
     * @return array
     */
    public function getUserByUsername($username, $attributes = [])
    {
        $searchLocations = $this->config()->users_search_locations ?: [null];
        foreach ($searchLocations as $searchLocation) {
            $records = $this->gateway->getUserByUsername($username, $searchLocation, Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes);
            if ($records) {
                return $records[0];
            }
        }
    }

    /**
     * Get a username for an email.
     *
     * @param string $email
     * @return string|null
     */
    public function getUsernameByEmail($email)
    {
        $data = $this->getUserByEmail($email);
        if (empty($data)) {
            return null;
        }

        return $this->gateway->getCanonicalUsername($data);
    }

    /**
     * Given a group DN, get the group membership data in LDAP.
     *
     * @param string $dn
     * @return array
     */
    public function getLDAPGroupMembers($dn)
    {
        $groupObj = Group::get()->filter('DN', $dn)->first();
        $groupData = $this->getGroupByGUID($groupObj->GUID);
        $members = !empty($groupData['member']) ? $groupData['member'] : [];
        // If a user belongs to a single group, this comes through as a string.
        // Normalise to a array so it's consistent.
        if ($members && is_string($members)) {
            $members = [$members];
        }

        return $members;
    }

    /**
     * Update the current Member record with data from LDAP.
     *
     * It's allowed to pass an unwritten Member record here, because it's not always possible to satisfy
     * field constraints without importing data from LDAP (for example if the application requires Username
     * through a Validator). Even though unwritten, it still must have the GUID set.
     *
     * Constraints:
     * - GUID of the member must have already been set, for integrity reasons we don't allow it to change here.
     *
     * @param Member
     * @param array|null $data If passed, this is pre-existing AD attribute data to update the Member with.
     *            If not given, the data will be looked up by the user's GUID.
     * @return bool
     */
    public function updateMemberFromLDAP(Member $member, $data = null)
    {
        if (!$this->enabled()) {
            return false;
        }

        if (!$member->GUID) {
            SS_Log::log(sprintf('Cannot update Member ID %s, GUID not set', $member->ID), SS_Log::WARN);
            return false;
        }

        if (!$data) {
            $data = $this->getUserByGUID($member->GUID);
            if (!$data) {
                SS_Log::log(sprintf('Could not retrieve data for user. GUID: %s', $member->GUID), SS_Log::WARN);
                return false;
            }
        }

        $member->IsExpired = ($data['useraccountcontrol'] & 2) == 2;
        $member->LastSynced = (string)SS_Datetime::now();

        foreach ($member->config()->ldap_field_mappings as $attribute => $field) {
            if (!isset($data[$attribute])) {
                SS_Log::log(sprintf(
                    'Attribute %s configured in Member.ldap_field_mappings, but no available attribute in AD data (GUID: %s, Member ID: %s)',
                    $attribute,
                    $data['objectguid'],
                    $member->ID
                ), SS_Log::NOTICE);

                continue;
            }

            if ($attribute == 'thumbnailphoto') {
                $imageClass = $member->getRelationClass($field);
                if ($imageClass !== 'Image' && !is_subclass_of($imageClass, 'Image')) {
                    SS_Log::log(sprintf(
                        'Member field %s configured for thumbnailphoto AD attribute, but it isn\'t a valid relation to an Image class',
                        $field
                    ), SS_Log::WARN);

                    continue;
                }

                $filename = sprintf('thumbnailphoto-%s.jpg', $data['samaccountname']);
                $path = ASSETS_DIR . '/' . $member->config()->ldap_thumbnail_path;
                $absPath = BASE_PATH . '/' . $path;
                if (!file_exists($absPath)) {
                    Filesystem::makeFolder($absPath);
                }

                // remove existing record if it exists
                $existingObj = $member->getComponent($field);
                if ($existingObj && $existingObj->exists()) {
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

        // if a default group was configured, ensure the user is in that group
        if ($this->config()->default_group) {
            $group = Group::get()->filter('Code', $this->config()->default_group)->limit(1)->first();
            if (!($group && $group->exists())) {
                SS_Log::log(
                    sprintf(
                        'LDAPService.default_group misconfiguration! There is no such group with Code = \'%s\'',
                        $this->config()->default_group
                    ),
                    SS_Log::WARN
                );
            } else {
                $group->Members()->add($member, [
                    'IsImportedFromLDAP' => '1'
                ]);
            }
        }

        // this is to keep track of which groups the user gets mapped to
        // and we'll use that later to remove them from any groups that they're no longer mapped to
        $mappedGroupIDs = [];

        // Member must have an ID before manipulating Groups, otherwise they will not be added correctly.
        // However we cannot do a full ->write before the groups are associated, because this will upsync
        // the Member, in effect deleting all their LDAP group associations!
        $member->writeWithoutSync();

        // ensure the user is in any mapped groups
        if (isset($data['memberof'])) {
            $ldapGroups = is_array($data['memberof']) ? $data['memberof'] : [$data['memberof']];
            foreach ($ldapGroups as $groupDN) {
                foreach (LDAPGroupMapping::get() as $mapping) {
                    if (!$mapping->DN) {
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
                    if ($mapping->DN == $groupDN) {
                        $group = $mapping->Group();
                        if ($group && $group->exists()) {
                            $group->Members()->add($member, [
                                'IsImportedFromLDAP' => '1'
                            ]);
                            $mappedGroupIDs[] = $mapping->GroupID;
                        }
                    }

                    // the user *might* be a member of a nested group provided the scope of the mapping
                    // is to include the entire subtree. Check all those mappings and find the LDAP child groups
                    // to see if they are a member of one of those. If they are, add them to the SS group
                    if ($mapping->Scope == 'Subtree') {
                        $childGroups = $this->getNestedGroups($mapping->DN, ['dn']);
                        if (!$childGroups) {
                            continue;
                        }

                        foreach ($childGroups as $childGroupDN => $childGroupRecord) {
                            if ($childGroupDN == $groupDN) {
                                $group = $mapping->Group();
                                if ($group && $group->exists()) {
                                    $group->Members()->add($member, [
                                        'IsImportedFromLDAP' => '1'
                                    ]);
                                    $mappedGroupIDs[] = $mapping->GroupID;
                                }
                            }
                        }
                    }
                }
            }
        }

        // remove the user from any previously mapped groups, where the mapping has since been removed
        $groupRecords = DB::query(sprintf('SELECT "GroupID" FROM "Group_Members" WHERE "IsImportedFromLDAP" = 1 AND "MemberID" = %s', $member->ID));
        foreach ($groupRecords as $groupRecord) {
            if (!in_array($groupRecord['GroupID'], $mappedGroupIDs)) {
                $group = Group::get()->byId($groupRecord['GroupID']);
                // Some groups may no longer exist. SilverStripe does not clean up join tables.
                if ($group) {
                    $group->Members()->remove($member);
                }
            }
        }
        // This will throw an exception if there are two distinct GUIDs with the same email address.
        // We are happy with a raw 500 here at this stage.
        $member->write();
    }

    /**
     * Sync a specific Group by updating it with LDAP data.
     *
     * @param Group $group An existing Group or a new Group object
     * @param array $data LDAP group object data
     *
     * @return bool
     */
    public function updateGroupFromLDAP(Group $group, $data)
    {
        if (!$this->enabled()) {
            return false;
        }

        // Synchronise specific guaranteed fields.
        $group->Code = $data['samaccountname'];
        $group->Title = $data['samaccountname'];
        if (!empty($data['description'])) {
            $group->Description = $data['description'];
        }
        $group->DN = $data['dn'];
        $group->LastSynced = (string)SS_Datetime::now();
        $group->write();

        // Mappings on this group are automatically maintained to contain just the group's DN.
        // First, scan through existing mappings and remove ones that are not matching (in case the group moved).
        $hasCorrectMapping = false;
        foreach ($group->LDAPGroupMappings() as $mapping) {
            if ($mapping->DN === $data['dn']) {
                // This is the correct mapping we want to retain.
                $hasCorrectMapping = true;
            } else {
                $mapping->delete();
            }
        }

        // Second, if the main mapping was not found, add it in.
        if (!$hasCorrectMapping) {
            $mapping = new LDAPGroupMapping();
            $mapping->DN = $data['dn'];
            $mapping->write();
            $group->LDAPGroupMappings()->add($mapping);
        }
    }

    /**
     * Creates a new LDAP user from the passed Member record.
     * Note that the Member record must have a non-empty Username field for this to work.
     *
     * @param Member $member
     */
    public function createLDAPUser(Member $member)
    {
        if (!$this->enabled()) {
            return;
        }
        if (empty($member->Username)) {
            throw new ValidationException('Member missing Username. Cannot create LDAP user');
        }
        if (!$this->config()->new_users_dn) {
            throw new Exception('LDAPService::new_users_dn must be configured to create LDAP users');
        }

        // Normalise username to lowercase to ensure we don't have duplicates of different cases
        $member->Username = strtolower($member->Username);

        // Create user in LDAP using available information.
        $dn = sprintf('CN=%s,%s', $member->Username, $this->config()->new_users_dn);

        try {
            $this->add($dn, [
                'objectclass' => 'user',
                'cn' => $member->Username,
                'accountexpires' => '9223372036854775807',
                'useraccountcontrol' => '66048',
                'userprincipalname' => sprintf(
                    '%s@%s',
                    $member->Username,
                    $this->gateway->config()->options['accountDomainName']
                ),
            ]);
        } catch (\Exception $e) {
            throw new ValidationException('LDAP synchronisation failure: '.$e->getMessage());
        }

        $user = $this->getUserByUsername($member->Username);
        if (empty($user['objectguid'])) {
            throw new ValidationException('LDAP synchronisation failure: user missing GUID');
        }

        // Creation was successful, mark the user as LDAP managed by setting the GUID.
        $member->GUID = $user['objectguid'];
    }

    /**
     * Creates a new LDAP group from the passed Group record.
     *
     * @param Group $group
     * @throws ValidationException
     */
    public function createLDAPGroup(Group $group) {
        if (!$this->enabled()) {
            return;
        }
        if (empty($group->Title)) {
            throw new ValidationException('Group missing Title. Cannot create LDAP group');
        }
        if (!$this->config()->new_groups_dn) {
            throw new Exception('LDAPService::new_groups_dn must be configured to create LDAP groups');
        }

        // LDAP isn't really meant to distinguish between a Title and Code. Squash them.
        $group->Code = $group->Title;

        $dn = sprintf('CN=%s,%s', $group->Title, $this->config()->new_groups_dn);
        try {
            $this->add($dn, [
                'objectclass' => 'group',
                'cn' => $group->Title,
                'name' => $group->Title,
                'samaccountname' => $group->Title,
                'description' => $group->Description,
                'distinguishedname' => $dn
            ]);
        } catch (\Exception $e) {
            throw new \ValidationException('LDAP group creation failure: ' . $e->getMessage());
        }

        $data = $this->getGroupByDN($dn);
        if (empty($data['objectguid'])) {
            throw new \ValidationException(
                new \ValidationResult(
                    false,
                    'LDAP group creation failure: group might have been created in LDAP. GUID is missing.'
                )
            );
        }

        // Creation was successful, mark the group as LDAP managed by setting the GUID.
        $group->GUID = $data['objectguid'];
        $group->DN = $data['dn'];
    }

    /**
     * Update the Member data back to the corresponding LDAP user object.
     *
     * @param Member $member
     * @throws ValidationException
     */
    public function updateLDAPFromMember(Member $member)
    {
        if (!$this->enabled()) {
            return;
        }
        if (!$member->GUID) {
            throw new ValidationException('Member missing GUID. Cannot update LDAP user');
        }

        $data = $this->getUserByGUID($member->GUID);
        if (empty($data['objectguid'])) {
            throw new ValidationException('LDAP synchronisation failure: user missing GUID');
        }

        if (empty($member->Username)) {
            throw new ValidationException('Member missing Username. Cannot update LDAP user');
        }

        $dn = $data['distinguishedname'];

        // Normalise username to lowercase to ensure we don't have duplicates of different cases
        $member->Username = strtolower($member->Username);

        try {
            // If the common name (cn) has changed, we need to ensure they've been moved
            // to the new DN, to avoid any clashes between user objects.
            if ($data['cn'] != $member->Username) {
                $newDn = sprintf('CN=%s,%s', $member->Username, preg_replace('/^CN=(.+?),/', '', $dn));
                $this->move($dn, $newDn);
                $dn = $newDn;
            }
        } catch (\Exception $e) {
            throw new ValidationException('LDAP move failure: '.$e->getMessage());
        }

        try {
            $attributes = [
                'displayname' => sprintf('%s %s', $member->FirstName, $member->Surname),
                'name' => sprintf('%s %s', $member->FirstName, $member->Surname),
                'userprincipalname' => sprintf(
                    '%s@%s',
                    $member->Username,
                    $this->gateway->config()->options['accountDomainName']
                ),
            ];
            foreach ($member->config()->ldap_field_mappings as $attribute => $field) {
                $relationClass = $member->getRelationClass($field);
                if ($relationClass) {
                    // todo no support for writing back relations yet.
                } else {
                    $attributes[$attribute] = $member->$field;
                }
            }

            $this->update($dn, $attributes);
        } catch (\Exception $e) {
            throw new ValidationException('LDAP synchronisation failure: '.$e->getMessage());
        }
    }

    /**
     * Ensure the user belongs to the correct groups in LDAP from their membership
     * to local LDAP mapped SilverStripe groups.
     *
     * This also removes them from LDAP groups if they've been taken out of one.
     * It will not affect group membership of non-mapped groups, so it will
     * not touch such internal AD groups like "Domain Users".
     *
     * @param Member $member
     */
    public function updateLDAPGroupsForMember(Member $member)
    {
        if (!$this->enabled()) {
            return;
        }
        if (!$member->GUID) {
            throw new ValidationException('Member missing GUID. Cannot update LDAP user');
        }

        $addGroups = [];
        $removeGroups = [];

        $user = $this->getUserByGUID($member->GUID);
        if (empty($user['objectguid'])) {
            throw new ValidationException('LDAP update failure: user missing GUID');
        }

        // If a user belongs to a single group, this comes through as a string.
        // Normalise to a array so it's consistent.
        $existingGroups = !empty($user['memberof']) ? $user['memberof'] : [];
        if ($existingGroups && is_string($existingGroups)) {
            $existingGroups = [$existingGroups];
        }

        foreach ($member->Groups() as $group) {
            if (!$group->GUID) {
                continue;
            }

            // mark this group as something we need to ensure the user belongs to in LDAP.
            $addGroups[] = $group->DN;
        }

        // Which existing LDAP groups are not in the add groups? We'll check these groups to
        // see if the user should be removed from any of them.
        $remainingGroups = array_diff($existingGroups, $addGroups);

        foreach ($remainingGroups as $groupDn) {
            // We only want to be removing groups we have a local Group mapped to. Removing
            // membership for anything else would be bad!
            $group = Group::get()->filter('DN', $groupDn)->first();
            if (!$group || !$group->exists()) {
                continue;
            }

            // this group should be removed from the user's memberof attribute, as it's been removed.
            $removeGroups[] = $groupDn;
        }

        // go through the groups we want the user to be in and ensure they're in them.
        foreach ($addGroups as $groupDn) {
            $this->addLDAPUserToGroup($user['distinguishedname'], $groupDn);
        }

        // go through the groups we _don't_ want the user to be in and ensure they're taken out of them.
        foreach ($removeGroups as $groupDn) {
            $members = $this->getLDAPGroupMembers($groupDn);

            // remove the user from the members data.
            if (in_array($user['distinguishedname'], $members)) {
                foreach ($members as $i => $dn) {
                    if ($dn == $user['distinguishedname']) {
                        unset($members[$i]);
                    }
                }
            }

            try {
                $this->update($groupDn, ['member' => $members]);
            } catch (\Exception $e) {
                throw new ValidationException('LDAP group membership remove failure: '.$e->getMessage());
            }
        }
    }

    /**
     * Add LDAP user by DN to LDAP group.
     *
     * @param string $userDn
     * @param string $groupDn
     * @throws \Exception
     */
    public function addLDAPUserToGroup($userDn, $groupDn) {
        $members = $this->getLDAPGroupMembers($groupDn);

        // this user is already in the group, no need to do anything.
        if (in_array($userDn, $members)) {
            return;
        }

        $members[] = $userDn;

        try {
            $this->update($groupDn, ['member' => $members]);
        } catch (\Exception $e) {
            throw new ValidationException('LDAP group membership add failure: '.$e->getMessage());
        }
    }

    /**
     * Change a members password on the AD. Works with ActiveDirectory compatible services that saves the
     * password in the `unicodePwd` attribute.
     *
     * @todo Use the Zend\Ldap\Attribute::setPassword functionality to create a password in
     * an abstract way, so it works on other LDAP directories, not just Active Directory.
     *
     * Ensure that the LDAP bind:ed user can change passwords and that the connection is secure.
     *
     * @param Member $member
     * @param string $password
     * @param string|null $oldPassword Supply old password to perform a password change (as opposed to password reset)
     * @return ValidationResult
     * @throws Exception
     */
    public function setPassword(Member $member, $password, $oldPassword = null)
    {
        $validationResult = ValidationResult::create(true);

        $this->extend('onBeforeSetPassword', $member, $password, $validationResult);

        if (!$member->GUID) {
            SS_Log::log(sprintf('Cannot update Member ID %s, GUID not set', $member->ID), SS_Log::WARN);
            $validationResult->error(_t('LDAPAuthenticator.NOUSER', 'Your account hasn\'t been setup properly, please contact an administrator.'));
            return $validationResult;
        }

        $userData = $this->getUserByGUID($member->GUID);
        if (empty($userData['distinguishedname'])) {
            $validationResult->error(_t('LDAPAuthenticator.NOUSER', 'Your account hasn\'t been setup properly, please contact an administrator.'));
            return $validationResult;
        }

        try {
            if (!empty($oldPassword)) {
                $this->gateway->changePassword($userData['distinguishedname'], $password, $oldPassword);
            } else if ($this->config()->password_history_workaround) {
                $this->passwordHistoryWorkaround($userData['distinguishedname'], $password);
            } else {
                $this->gateway->resetPassword($userData['distinguishedname'], $password);
            }
            $this->extend('onAfterSetPassword', $member, $password, $validationResult);
        } catch (Exception $e) {
            $validationResult->error($e->getMessage());
        }

        return $validationResult;
    }

    /**
     * Delete an LDAP user mapped to the Member record
     * @param Member $member
     */
    public function deleteLDAPMember(Member $member) {
        if (!$this->enabled()) {
            return;
        }
        if (!$member->GUID) {
            throw new ValidationException('Member missing GUID. Cannot delete LDAP user');
        }
        $data = $this->getUserByGUID($member->GUID);
        if (empty($data['distinguishedname'])) {
            throw new ValidationException('LDAP delete failure: could not find distinguishedname attribute');
        }

        try {
            $this->delete($data['distinguishedname']);
        } catch (\Exception $e) {
            throw new ValidationException('LDAP delete user failed: '.$e->getMessage());
        }
    }

    /**
     * A simple proxy to LDAP update operation.
     *
     * @param string $dn Location to add the entry at.
     * @param array $attributes A simple associative array of attributes.
     */
    public function update($dn, array $attributes)
    {
        $this->gateway->update($dn, $attributes);
    }

    /**
     * A simple proxy to LDAP delete operation.
     *
     * @param string $dn Location of object to delete
     * @param bool $recursively Recursively delete nested objects?
     */
    public function delete($dn, $recursively = false)
    {
        $this->gateway->delete($dn, $recursively);
    }

    /**
     * A simple proxy to LDAP copy/delete operation.
     *
     * @param string $fromDn
     * @param string $toDn
     * @param bool $recursively Recursively move nested objects?
     */
    public function move($fromDn, $toDn, $recursively = false)
    {
        $this->gateway->move($fromDn, $toDn, $recursively);
    }

    /**
     * A simple proxy to LDAP add operation.
     *
     * @param string $dn Location to add the entry at.
     * @param array $attributes A simple associative array of attributes.
     */
    public function add($dn, array $attributes)
    {
        $this->gateway->add($dn, $attributes);
    }

    /**
     * @param string $dn Distinguished name of the user
     * @param string $password New password.
     * @throws Exception
     */
    private function passwordHistoryWorkaround($dn, $password) {
        $generator = new RandomGenerator();
        // 'Aa1' is there to satisfy the complexity criterion.
        $tempPassword = sprintf('Aa1%s', substr($generator->randomToken('sha1'), 0, 21));
        $this->gateway->resetPassword($dn, $tempPassword);
        $this->gateway->changePassword($dn, $password, $tempPassword);
    }

}
