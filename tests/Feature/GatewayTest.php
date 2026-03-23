<?php

namespace Omnipay\VakifKatilim\Tests\Feature;

use Omnipay\VakifKatilim\Gateway;
use Omnipay\VakifKatilim\Message\CompletePurchaseRequest;
use Omnipay\VakifKatilim\Message\PurchaseRequest;
use Omnipay\VakifKatilim\Tests\TestCase;

class GatewayTest extends TestCase
{
	public function test_gateway_name()
	{
		$this->assertEquals('VakifKatilim', $this->gateway->getName());
	}

	public function test_gateway_default_parameters()
	{
		$defaults = $this->gateway->getDefaultParameters();

		$this->assertArrayHasKey('clientIp', $defaults);
		$this->assertArrayHasKey('merchantId', $defaults);
		$this->assertArrayHasKey('customerId', $defaults);
		$this->assertArrayHasKey('userName', $defaults);
		$this->assertArrayHasKey('password', $defaults);
		$this->assertArrayHasKey('installment', $defaults);
		$this->assertArrayHasKey('secure', $defaults);

		$this->assertEquals('127.0.0.1', $defaults['clientIp']);
		$this->assertTrue($defaults['secure']);
	}

	public function test_gateway_purchase_returns_correct_request()
	{
		$request = $this->gateway->purchase([]);

		$this->assertInstanceOf(PurchaseRequest::class, $request);
	}

	public function test_gateway_complete_purchase_returns_correct_request()
	{
		$request = $this->gateway->completePurchase([]);

		$this->assertInstanceOf(CompletePurchaseRequest::class, $request);
	}

	public function test_gateway_getters_setters()
	{
		$this->gateway->setMerchantId('1');
		$this->assertEquals('1', $this->gateway->getMerchantId());

		$this->gateway->setCustomerId('400235');
		$this->assertEquals('400235', $this->gateway->getCustomerId());

		$this->gateway->setUserName('apiuser');
		$this->assertEquals('apiuser', $this->gateway->getUserName());

		$this->gateway->setPassword('Api123');
		$this->assertEquals('Api123', $this->gateway->getPassword());

		$this->gateway->setInstallment(3);
		$this->assertEquals(3, $this->gateway->getInstallment());

		$this->gateway->setSecure(false);
		$this->assertFalse($this->gateway->getSecure());

		$this->gateway->setClientIp('192.168.1.1');
		$this->assertEquals('192.168.1.1', $this->gateway->getClientIp());
	}
}
