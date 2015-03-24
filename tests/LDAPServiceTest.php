<?php
class LDAPServiceTest extends SapphireTest {

	protected $service;

	public function setUp() {
		parent::setUp();

		$gateway = new LDAPFakeGateway();
		Injector::inst()->registerService($gateway, 'LDAPGateway');

		$service = Injector::inst()->create('LDAPService');
		$service->setGateway($gateway);
		$this->service = $service;

		Config::inst()->nest();
		Config::inst()->update('LDAPService', 'search_locations', array(
			'CN=Users,DC=playpen,DC=local',
			'CN=Others,DC=playpen,DC=local'
		));
		Config::inst()->update('Member', 'ldap_field_mappings', array(
			'givenname' => 'FirstName',
			'sn' => 'Surname',
			'mail' => 'Email'
		));
	}

	public function tearDown() {
		parent::tearDown();

		Config::inst()->unnest();
	}

	public function testGroups() {
		$expected = array(
			'CN=Group1,CN=Users,DC=playpen,DC=local' => array('dn' => 'CN=Group1,CN=Users,DC=playpen,DC=local'),
			'CN=Group2,CN=Users,DC=playpen,DC=local' => array('dn' => 'CN=Group2,CN=Users,DC=playpen,DC=local'),
			'CN=Group3,CN=Users,DC=playpen,DC=local' => array('dn' => 'CN=Group3,CN=Users,DC=playpen,DC=local'),
			'CN=Group4,CN=Users,DC=playpen,DC=local' => array('dn' => 'CN=Group4,CN=Users,DC=playpen,DC=local'),
			'CN=Group5,CN=Users,DC=playpen,DC=local' => array('dn' => 'CN=Group5,CN=Users,DC=playpen,DC=local'),
			'CN=Group6,CN=Others,DC=playpen,DC=local' => array('dn' => 'CN=Group6,CN=Others,DC=playpen,DC=local'),
			'CN=Group7,CN=Others,DC=playpen,DC=local' => array('dn' => 'CN=Group7,CN=Others,DC=playpen,DC=local'),
			'CN=Group8,CN=Others,DC=playpen,DC=local' => array('dn' => 'CN=Group8,CN=Others,DC=playpen,DC=local')
		);

		$results = $this->service->getGroups();

		$this->assertEquals($expected, $results);
	}

	public function testUpdateMemberFromLDAP() {
		$member = new Member();
		$member->GUID = '123';

		$result = $this->service->updateMemberFromLDAP($member);
		$this->assertTrue($result, 'Sync was successful');

		$this->assertTrue($member->ID > 0, 'updateMemberFromLDAP writes the member');
		$this->assertEquals('123', $member->GUID, 'GUID remains the same');
		$this->assertEquals('Joe', $member->FirstName, 'FirstName updated from LDAP');
		$this->assertEquals('Bloggs', $member->Surname, 'Surname updated from LDAP');
		$this->assertEquals('joe@bloggs.com', $member->Email, 'Email updated from LDAP');

		$member->delete();
	}

	public function testUpdateMemberFromLDAPSkippedAlreadyImported() {
		$now = SS_Datetime::now();

		$member = new Member();
		$member->GUID = '123';
		$member->LastEditedLDAP = $now;
		$member->write();

		LDAPFakeGateway::$data['users']['123']['whenchanged'] = $now;

		$result = $this->service->updateMemberFromLDAP($member);
		$this->assertFalse($result, 'Sync was not run');

		LDAPFakeGateway::$data['users']['123']['whenchanged'] = date('Y-m-d H:i:s', strtotime('+1 month'));

		$result = $this->service->updateMemberFromLDAP($member);
		$this->assertTrue($result, 'Sync was successful');

		$member->LastEditedLDAP = '2014-01-01 00:00:00';
		$member->write();

		LDAPFakeGateway::$data['users']['123']['whenchanged'] = $now;

		$result = $this->service->updateMemberFromLDAP($member);
		$this->assertTrue($result, 'Sync was successful');

		$member->delete();
	}

}
