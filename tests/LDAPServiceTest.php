<?php

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\Member;

/**
 * @coversDefaultClass \SilverStripe\ActiveDirectory\Services\LDAPService
 * @package activedirectory
 */
class LDAPServiceTest extends SapphireTest
{
    /**
     * @var LDAPService
     */
    protected $service;

    /**
     * {@inheritDoc}
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        parent::setUp();

        $gateway = new \LDAPFakeGateway();
        Injector::inst()->registerService($gateway, 'SilverStripe\\ActiveDirectory\\Model\\LDAPGateway');

        $service = Injector::inst()->create('SilverStripe\\ActiveDirectory\\Services\\LDAPService');
        $service->setGateway($gateway);
        $this->service = $service;

        Config::inst()->nest();
        Config::inst()->update('SilverStripe\\ActiveDirectory\\Model\\LDAPGateway', 'options', ['host' => '1.2.3.4']);
        Config::inst()->update('SilverStripe\\ActiveDirectory\\Services\\LDAPService', 'groups_search_locations', [
            'CN=Users,DC=playpen,DC=local',
            'CN=Others,DC=playpen,DC=local'
        ]);
        // Prevent other module extension hooks from executing during write() etc.
        Config::inst()->remove('SilverStripe\\Security\\Member', 'extensions');
        Config::inst()->remove('SilverStripe\\Security\\Group', 'extensions');
        Config::inst()->update('SilverStripe\\Security\\Member', 'update_ldap_from_local', false);
        Config::inst()->update('SilverStripe\\Security\\Member', 'create_users_in_ldap', false);
        Config::inst()->update(
            'SilverStripe\\Security\\Group',
            'extensions',
            ['SilverStripe\\ActiveDirectory\\Extensions\\LDAPGroupExtension']
        );
        Config::inst()->update(
            'SilverStripe\\Security\\Member',
            'extensions',
            ['SilverStripe\\ActiveDirectory\\Extensions\\LDAPMemberExtension']
        );

        // Disable Monolog logging to stderr by default if you don't give it a handler
        $this->service->getLogger()->pushHandler(new \Monolog\Handler\NullHandler);
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown()
    {
        parent::tearDown();

        Config::inst()->unnest();
    }

    public function testGroups()
    {
        $expected = [
            'CN=Group1,CN=Users,DC=playpen,DC=local' => ['dn' => 'CN=Group1,CN=Users,DC=playpen,DC=local'],
            'CN=Group2,CN=Users,DC=playpen,DC=local' => ['dn' => 'CN=Group2,CN=Users,DC=playpen,DC=local'],
            'CN=Group3,CN=Users,DC=playpen,DC=local' => ['dn' => 'CN=Group3,CN=Users,DC=playpen,DC=local'],
            'CN=Group4,CN=Users,DC=playpen,DC=local' => ['dn' => 'CN=Group4,CN=Users,DC=playpen,DC=local'],
            'CN=Group5,CN=Users,DC=playpen,DC=local' => ['dn' => 'CN=Group5,CN=Users,DC=playpen,DC=local'],
            'CN=Group6,CN=Others,DC=playpen,DC=local' => ['dn' => 'CN=Group6,CN=Others,DC=playpen,DC=local'],
            'CN=Group7,CN=Others,DC=playpen,DC=local' => ['dn' => 'CN=Group7,CN=Others,DC=playpen,DC=local'],
            'CN=Group8,CN=Others,DC=playpen,DC=local' => ['dn' => 'CN=Group8,CN=Others,DC=playpen,DC=local']
        ];

        $results = $this->service->getGroups();

        $this->assertEquals($expected, $results);
    }

    public function testUpdateMemberFromLDAP()
    {
        Config::inst()->update(
            'SilverStripe\\Security\\Member',
            'ldap_field_mappings',
            [
                'givenname' => 'FirstName',
                'sn' => 'Surname',
                'mail' => 'Email',
            ]
        );

        $member = new Member();
        $member->GUID = '123';

        $this->service->updateMemberFromLDAP($member);

        $this->assertTrue($member->ID > 0, 'updateMemberFromLDAP writes the member');
        $this->assertEquals('123', $member->GUID, 'GUID remains the same');
        $this->assertEquals('Joe', $member->FirstName, 'FirstName updated from LDAP');
        $this->assertEquals('Bloggs', $member->Surname, 'Surname updated from LDAP');
        $this->assertEquals('joe@bloggs.com', $member->Email, 'Email updated from LDAP');
    }
}
