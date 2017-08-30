<?php

namespace SilverStripe\ActiveDirectory\Tests\Services;

use SilverStripe\ActiveDirectory\Extensions\LDAPGroupExtension;
use SilverStripe\ActiveDirectory\Extensions\LDAPMemberExtension;
use SilverStripe\ActiveDirectory\Model\LDAPGateway;
use SilverStripe\ActiveDirectory\Services\LDAPService;
use SilverStripe\ActiveDirectory\Tests\FakeGatewayTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;

/**
 * @coversDefaultClass \SilverStripe\ActiveDirectory\Services\LDAPService
 * @package activedirectory
 */
class LDAPServiceTest extends FakeGatewayTest
{
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

        Config::modify()->set(LDAPGateway::class, 'options', ['host' => '1.2.3.4']);
        Config::modify()->set(LDAPService::class, 'groups_search_locations', [
            'CN=Users,DC=playpen,DC=local',
            'CN=Others,DC=playpen,DC=local'
        ]);
        // Prevent other module extension hooks from executing during write() etc.
        Config::modify()->remove(Member::class, 'extensions');
        Config::modify()->remove(Group::class, 'extensions');
        Config::modify()->set(Member::class, 'update_ldap_from_local', false);
        Config::modify()->set(Member::class, 'create_users_in_ldap', false);
        Config::modify()->set(
            Group::class,
            'extensions',
            [LDAPGroupExtension::class]
        );
        Config::modify()->set(
            Member::class,
            'extensions',
            [LDAPMemberExtension::class]
        );

        // Disable Monolog logging to stderr by default if you don't give it a handler
        $this->service->getLogger()->pushHandler(new \Monolog\Handler\NullHandler);
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
        Config::modify()->set(
            Member::class,
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
