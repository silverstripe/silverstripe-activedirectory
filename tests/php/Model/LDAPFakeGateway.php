<?php

namespace SilverStripe\ActiveDirectory\Tests\Model;

use Zend\Authentication\Result as AuthenticationResult;
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
                'mail' => 'joe@bloggs.com',
                'password' => 'mockPassword',
                'canonicalName'=>'mockCanonicalName',
                'userprincipalname' => 'joe@bloggs.com',
                'samaccountname' => 'joe'
            ]
        ]
    ];

    /**
     * @inheritdoc
     */
    public function authenticate($username, $password)
    {
        $messages = [];
        if (!$user = $this->getUserByEmail($username)) {
            $messages[0] = 'Username not found';
            $code = AuthenticationResult::FAILURE;
            return new AuthenticationResult($code, $username, $messages);
        }
        if ($user[0]['password'] == $password) {
            $messages[0] = 'OK';
            return new AuthenticationResult(AuthenticationResult::SUCCESS, $username, $messages);
        } else {
            $messages[0] = 'Password doesn\'t match';
            return new AuthenticationResult(AuthenticationResult::FAILURE, $username, $messages);
        }
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

    protected function search($filter, $baseDn = null, $scope = Ldap::SEARCH_SCOPE_SUB, $attributes = [], $sort = '')
    {
        $records = self::$data;
        $results = [];
        foreach ($records as $record) {
            foreach ($record as $attribute => $value) {
                // if the value is an array with a single value, e.g. 'samaccountname' => array(0 => 'myusername')
                // then make sure it's just set in the results as 'samaccountname' => 'myusername' so that it
                // can be used directly by ArrayData
                if (is_array($value) && count($value) == 1) {
                    $value = $value[0];
                }

                // ObjectGUID and ObjectSID attributes are in binary, we need to convert those to strings
                if ($attribute == 'objectguid') {
                    $value = LDAPUtil::bin_to_str_guid($value);
                }
                if ($attribute == 'objectsid') {
                    $value = LDAPUtil::bin_to_str_sid($value);
                }

                $record[$attribute] = $value;
            }

            $results[] = $record;
        }

        return $results;
    }

    /**
     * Mock to search trough dummy $data.
     *
     * @param string $email
     * @param null $baseDn
     * @param int $scope
     * @param array $attributes
     * @return array
     */
    public function getUserByEmail($email, $baseDn = null, $scope = Ldap::SEARCH_SCOPE_SUB, $attributes = [])
    {
        $result = [];
        foreach (self::$data['users'] as $guid => $info) {
            if ($info['mail'] == $email) {
                $result[] = $info;
                break;
            }
        }

        return $result;
    }

    /**
     * Mock to search trough dummy $data.
     *
     * @param string $username
     * @param null $baseDn
     * @param int $scope
     * @param array $attributes
     * @return array
     * @internal param string $email
     */
    public function getUserByUsername($username, $baseDn = null, $scope = Ldap::SEARCH_SCOPE_SUB, $attributes = [])
    {
        $result = [];
        foreach (self::$data['users'] as $guid => $info) {
            if ($info['userprincipalname'] == $username) {
                $result[] = $info;
                break;
            }
        }

        return $result;
    }
}
