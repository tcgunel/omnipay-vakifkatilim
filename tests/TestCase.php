<?php

namespace Omnipay\VakifKatilim\Tests;

use Faker\Factory;
use Omnipay\Tests\GatewayTestCase;
use Omnipay\VakifKatilim\Gateway;

class TestCase extends GatewayTestCase
{
    public $faker;

    public $gateway;

    public function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create('tr_TR');

        $this->gateway = new Gateway($this->getHttpClient(), $this->getHttpRequest());
    }
}
