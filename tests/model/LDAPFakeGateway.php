<?php
class LDAPFakeGateway extends LDAPGateway implements TestOnly
{
    public function __construct()
    {
        // do nothing
    }

    private static $data = array(
        'groups' => array(
            'CN=Users,DC=playpen,DC=local' => array(
                array('dn' => 'CN=Group1,CN=Users,DC=playpen,DC=local'),
                array('dn' => 'CN=Group2,CN=Users,DC=playpen,DC=local'),
                array('dn' => 'CN=Group3,CN=Users,DC=playpen,DC=local'),
                array('dn' => 'CN=Group4,CN=Users,DC=playpen,DC=local'),
                array('dn' => 'CN=Group5,CN=Users,DC=playpen,DC=local')
            ),
            'CN=Others,DC=playpen,DC=local' => array(
                array('dn' => 'CN=Group6,CN=Others,DC=playpen,DC=local'),
                array('dn' => 'CN=Group7,CN=Others,DC=playpen,DC=local'),
                array('dn' => 'CN=Group8,CN=Others,DC=playpen,DC=local')
            )
        ),
        'users' => array(
            '123' => array(
                'objectguid' => '123',
                'useraccountcontrol' => '1',
                'givenname' => 'Joe',
                'sn' => 'Bloggs',
                'mail' => 'joe@bloggs.com'
            )
        )
    );

    public function authenticate($username, $password)
    {
    }

    public function getNodes($baseDn = null, $scope = Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes = array(), $sort = '')
    {
    }

    public function getGroups($baseDn = null, $scope = Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes = array(), $sort = '')
    {
        if (isset($baseDn)) {
            return !empty(self::$data['groups'][$baseDn]) ? self::$data['groups'][$baseDn] : null;
        }
    }

    public function getNestedGroups($dn, $baseDn = null, $scope = Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes = array())
    {
    }

    public function getGroupByGUID($guid, $baseDn = null, $scope = Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes = array())
    {
    }

    public function getUsers($baseDn = null, $scope = Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes = array(), $sort = '')
    {
    }

    public function getUserByGUID($guid, $baseDn = null, $scope = Zend\Ldap\Ldap::SEARCH_SCOPE_SUB, $attributes = array())
    {
        return array(self::$data['users'][$guid]);
    }
}
