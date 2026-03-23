<?php

namespace Omnipay\VakifKatilim;

use Omnipay\Common\AbstractGateway;
use Omnipay\VakifKatilim\Message\CompletePurchaseRequest;
use Omnipay\VakifKatilim\Message\PurchaseRequest;
use Omnipay\VakifKatilim\Traits\PurchaseGettersSetters;

/**
 * VakifKatilim Gateway
 * (c) Tolga Can Gunel
 * 2015, mobius.studio
 * http://www.github.com/tcgunel/omnipay-vakifkatilim
 * @method \Omnipay\Common\Message\NotificationInterface acceptNotification(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface completeAuthorize(array $options = [])
 */
class Gateway extends AbstractGateway
{
	use PurchaseGettersSetters;

	public function getName(): string
	{
		return 'VakifKatilim';
	}

	public function getDefaultParameters()
	{
		return [
			'clientIp'    => '127.0.0.1',
			'merchantId'  => '',
			'customerId'  => '',
			'userName'    => '',
			'password'    => '',
			'installment' => 0,
			'secure'      => true,
		];
	}

	public function purchase(array $options = [])
	{
		return $this->createRequest(PurchaseRequest::class, $options);
	}

	public function completePurchase(array $options = [])
	{
		return $this->createRequest(CompletePurchaseRequest::class, $options);
	}
}
