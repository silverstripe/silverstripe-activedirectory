<?php

namespace SilverStripe\ActiveDirectory\Tests;

use SilverStripe\ActiveDirectory\Model\LDAPGateway;
use SilverStripe\ActiveDirectory\Services\LDAPService;
use SilverStripe\ActiveDirectory\Tests\Model\LDAPFakeGateway;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

abstract class FakeGatewayTest extends SapphireTest
{
    /**
     * @var LDAPService
     */
    protected $service;

    protected function setUp()
    {
        parent::setUp();

        $gateway = new LDAPFakeGateway();
        Injector::inst()->registerService($gateway, LDAPGateway::class);

        $service = Injector::inst()->get(LDAPService::class);
        $service->setGateway($gateway);

        $this->service = $service;
    }
}
