<?php

use SilverStripe\ActiveDirectory\Model\LDAPGateway;
use SilverStripe\Dev\TestOnly;
use Zend\Ldap\Ldap;

/**
 * @package activedirectory
 */
class LDAPFakeGateway extends LDAPGateway implements TestOnly
{
    public function __construct()
    {
        // do nothing
    }

    private static $data = [
        'groups' => [
            'CN=Users,DC=playpen,DC=local' => [
                ['dn' => 'CN=Group1,CN=Users,DC=playpen,DC=local'],
                ['dn' => 'CN=Group2,CN=Users,DC=playpen,DC=local'],
                ['dn' => 'CN=Group3,CN=Users,DC=playpen,DC=local'],
                ['dn' => 'CN=Group4,CN=Users,DC=playpen,DC=local'],
                ['dn' => 'CN=Group5,CN=Users,DC=playpen,DC=local']
            ],
            'CN=Others,DC=playpen,DC=local' => [
                ['dn' => 'CN=Group6,CN=Others,DC=playpen,DC=local'],
                ['dn' => 'CN=Group7,CN=Others,DC=playpen,DC=local'],
                ['dn' => 'CN=Group8,CN=Others,DC=playpen,DC=local']
            ]
        ],
        'users' => [
            '123' => [
                'distinguishedname' => 'CN=Joe,DC=playpen,DC=local',
                'objectguid' => '123',
                'cn' => 'jbloggs',
                'useraccountcontrol' => '1',
                'givenname' => 'Joe',
                'sn' => 'Bloggs',
                'mail' => 'joe@bloggs.com'
            ]
        ]
    ];

    public function authenticate($username, $password)
    {
    }

    public function getNodes($baseDn = null, $scope = Ldap::SEARCH_SCOPE_SUB, $attributes = [], $sort = '')
    {
    }

    public function getGroups($baseDn = null, $scope = Ldap::SEARCH_SCOPE_SUB, $attributes = [], $sort = '')
    {
        if (isset($baseDn)) {
            return !empty(self::$data['groups'][$baseDn]) ? self::$data['groups'][$baseDn] : null;
        }
    }

    public function getNestedGroups($dn, $baseDn = null, $scope = Ldap::SEARCH_SCOPE_SUB, $attributes = [])
    {
    }

    public function getGroupByGUID($guid, $baseDn = null, $scope = Ldap::SEARCH_SCOPE_SUB, $attributes = [])
    {
    }

    public function getUsers($baseDn = null, $scope = Ldap::SEARCH_SCOPE_SUB, $attributes = [], $sort = '')
    {
    }

    public function getUserByGUID($guid, $baseDn = null, $scope = Ldap::SEARCH_SCOPE_SUB, $attributes = [])
    {
        return [self::$data['users'][$guid]];
    }

    public function update($dn, array $attributes)
    {
    }

    public function delete($dn, $recursively = false)
    {
    }

    public function move($fromDn, $toDn, $recursively = false)
    {
    }

    public function add($dn, array $attributes)
    {
    }
}
