<?php

use Mockery\MockInterface;

class LDAPAuthenticatorTest extends SapphireTest
{
    // internal test string constants
    const t_username = 'denvercoder9';
    const t_password = 'Correct Horse Battery Staple';
    const t_email = 'denvercoder@example.com';
    const t_backurl = '/redirect-here-after-login';

    // for testing the default fallback authenticator MemberAuthenticator
    protected $usesDatabase = true;

    /**
     * @var MockInterface
     */
    protected $service;

    /**
     * For mocking fallback authenticator return value.
     *
     * @var Member
     */
    private static $fallback_auth_value;

    public function setUp()
    {
        parent::setUp();

        $this->service = Mockery::mock(LDAPService::class);
        $this->service->shouldReceive('setGateway');
        Injector::inst()->registerService($this->service, 'LDAPService');

        Config::inst()->nest();
        Config::inst()->remove('Member', 'extensions');
        Config::inst()->remove('Group', 'extensions');
        Config::inst()->update('Member', 'update_ldap_from_local', false);
        Config::inst()->update('Member', 'create_users_in_ldap', false);
    }

    public function tearDown()
    {
        self::$fallback_auth_value = null;
        Config::inst()->unnest();
        parent::tearDown();
    }

    public function testLoginSuccess()
    {
        Session::set('BackURL', self::t_backurl);
        $this->service->shouldReceive('authenticate')->andReturn([
            'success' => true,
            'identity' => self::t_username,
        ]);
        $this->service->shouldReceive('getUserByUsername')->andReturn(['objectguid' => '123456']);
        $this->service->shouldReceive('updateMemberFromLDAP')->andReturn(true);

        $data = ['Login' => self::t_username, 'Password' => self::t_password];
        $form = \Form::create(Controller::create(), 'name', new FieldList(), new FieldList());

        $res = LDAPAuthenticator::authenticate($data, $form);

        $this->assertTrue($res instanceof Member);
        $this->assertNull(Session::get('BackURL'));
    }

    public function testLoginWithEmailSuccess()
    {
        Config::inst()->update('LDAPAuthenticator', 'allow_email_login', 'yes');
        $this->service->shouldReceive('getUsernameByEmail')->andReturn(self::t_username);
        $this->service->shouldReceive('authenticate')->andReturn([
            'success' => true,
            'identity' => self::t_username,
        ]);
        $this->service->shouldReceive('getUserByUsername')->andReturn(['objectguid' => '123456']);
        $this->service->shouldReceive('updateMemberFromLDAP')->andReturn(true);

        $data = ['Login' => self::t_email, 'Password' => self::t_password];
        $form = \Form::create(Controller::create(), 'name', new FieldList(), new FieldList());

        $res = LDAPAuthenticator::authenticate($data, $form);

        $this->assertTrue($res instanceof Member);
    }

    public function testUsernameOnly()
    {
        Config::inst()->update('LDAPAuthenticator', 'allow_email_login', 'no');
        $this->service->shouldReceive('getUsernameByEmail')->andReturn(self::t_username);

        $data = ['Login' => self::t_email, 'Password' => self::t_password];
        $form = \Form::create(Controller::create(), 'name', new FieldList(), new FieldList());

        LDAPAuthenticator::authenticate($data, $form);

        $this->assertEquals('Please enter your username instead of your email to log in.', $form->Message());
    }

    public function testEmailNoUsernameNoFallbackAuth()
    {
        Session::set('BackURL', self::t_backurl);
        Config::inst()->update('LDAPAuthenticator', 'allow_email_login', 'yes');
        Config::inst()->update('LDAPAuthenticator', 'fallback_authenticator', 'no');
        $this->service->shouldReceive('getUsernameByEmail')->andReturn(false);

        $data = ['Login' => self::t_email, 'Password' => self::t_password];
        $form = \Form::create(Controller::create(), 'name', new FieldList(), new FieldList());

        LDAPAuthenticator::authenticate($data, $form);

        $this->assertEquals('Invalid credentials', $form->Message());
        $this->assertEquals(self::t_backurl, Session::get('BackURL'));
    }

    public function testEmailLoginToFallbackAuthFailed()
    {
        Config::inst()->update('LDAPAuthenticator', 'allow_email_login', 'yes');
        Config::inst()->update('LDAPAuthenticator', 'fallback_authenticator', 'yes');
        $this->service->shouldReceive('getUsernameByEmail')->andReturn(false);

        $data = ['Login' => self::t_email, 'Password' => self::t_password];
        $form = \Form::create(Controller::create(), 'name', new FieldList(), new FieldList());

        LDAPAuthenticator::authenticate($data, $form);

        $this->assertEquals('Invalid credentials', $form->Message());
    }

    public function testEmailLoginToFallbackAuthSuccess()
    {
        Config::inst()->update('LDAPAuthenticator', 'allow_email_login', 'yes');
        Config::inst()->update('LDAPAuthenticator', 'fallback_authenticator', 'yes');
        $user = Member::create(['Email' => self::t_email]);
        $user->changePassword(self::t_password); // will write user to database
        $this->service->shouldReceive('getUsernameByEmail')->andReturn(false);

        $data = ['Login' => $user->Email, 'Password' => self::t_password];
        $form = \Form::create(Controller::create(), 'name', new FieldList(), new FieldList());

        $res = LDAPAuthenticator::authenticate($data, $form);

        $this->assertTrue(0 != $res->ID);
        $this->assertEquals($res->ID, $user->ID);
    }

    public function testCannotFetchUserData()
    {
        $this->service->shouldReceive('authenticate')->andReturn([
            'success' => true,
            'identity' => self::t_username,
        ]);
        $this->service->shouldReceive('getUserByUsername')->andReturn([]);

        $data = ['Login' => self::t_username, 'Password' => self::t_password];
        $form = \Form::create(Controller::create(), 'name', new FieldList(), new FieldList());

        $res = LDAPAuthenticator::authenticate($data, $form);
        $this->assertNull($res);
        $this->assertEquals('There was a problem retrieving your user data', $form->Message());
    }

    public function testFallbackAuthenticatorUsernameLoginSuccess()
    {
        Config::inst()->update('LDAPAuthenticator', 'allow_email_login', 'yes');
        Config::inst()->update('LDAPAuthenticator', 'fallback_authenticator', 'yes');
        // set this test class to act as a fallback authenticator
        Config::inst()->update('LDAPAuthenticator', 'fallback_authenticator_class', self::class);
        $user = Member::create(['Email' => self::t_email]);
        self::set_fallback_authenticator_value($user);
        $this->service->shouldReceive('authenticate')->andReturn(['success' => false]);

        $data = ['Login' => self::t_username, 'Password' => self::t_password];
        $form = \Form::create(Controller::create(), 'name', new FieldList(), new FieldList());

        $res = LDAPAuthenticator::authenticate($data, $form);

        $this->assertEquals($res->Email, $user->Email);
    }

    public function testFallbackAuthenticatorUsernameLoginFailure()
    {
        Config::inst()->update('LDAPAuthenticator', 'allow_email_login', 'yes');
        Config::inst()->update('LDAPAuthenticator', 'fallback_authenticator', 'yes');
        // set this test class to act as a fallback authenticator
        Config::inst()->update('LDAPAuthenticator', 'fallback_authenticator_class', self::class);
        self::set_fallback_authenticator_value(false);
        $this->service->shouldReceive('authenticate')->andReturn(['success' => false, 'message' => 'oh noe']);

        $data = ['Login' => self::t_username, 'Password' => self::t_password];
        $form = \Form::create(Controller::create(), 'name', new FieldList(), new FieldList());

        $res = LDAPAuthenticator::authenticate($data, $form);

        $this->assertNull($res);
        $this->assertEquals('oh noe', $form->Message());
    }

    public static function authenticate($data, Form $form = null)
    {
        return self::$fallback_auth_value;
    }

    private function set_fallback_authenticator_value($user)
    {
        self::$fallback_auth_value = $user;
    }
}
