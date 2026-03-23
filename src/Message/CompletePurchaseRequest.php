<?php

namespace Omnipay\VakifKatilim\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\VakifKatilim\Constants\CurrencyCode;
use Omnipay\VakifKatilim\Helpers\Helper;
use Omnipay\VakifKatilim\Traits\PurchaseGettersSetters;

class CompletePurchaseRequest extends AbstractRequest
{
	use PurchaseGettersSetters;

	protected $prod_endpoint = 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelProvisionGate';

	/**
	 * Get the ResponseCode from the 3D callback.
	 */
	public function getResponseCode()
	{
		return $this->getParameter('responseCode');
	}

	public function setResponseCode($value)
	{
		return $this->setParameter('responseCode', $value);
	}

	/**
	 * Get the ResponseMessage from the 3D callback.
	 */
	public function getResponseMessage()
	{
		return $this->getParameter('responseMessage');
	}

	public function setResponseMessage($value)
	{
		return $this->setParameter('responseMessage', $value);
	}

	/**
	 * Get the MerchantOrderId from the 3D callback.
	 */
	public function getMerchantOrderId()
	{
		return $this->getParameter('merchantOrderId');
	}

	public function setMerchantOrderId($value)
	{
		return $this->setParameter('merchantOrderId', $value);
	}

	/**
	 * Get the MD (Message Digest) from the 3D callback.
	 */
	public function getMd()
	{
		return $this->getParameter('md');
	}

	public function setMd($value)
	{
		return $this->setParameter('md', $value);
	}

	/**
	 * @throws InvalidRequestException
	 */
	public function getData()
	{
		$this->validate(
			'merchantId',
			'customerId',
			'userName',
			'password',
		);

		$responseCode = $this->getResponseCode();

		// Check if 3D authentication was successful
		if ($responseCode !== '00') {
			return [
				'_authSuccess'    => false,
				'ResponseCode'    => $responseCode ?? 'AuthFail',
				'ResponseMessage' => $this->getResponseMessage() ?? '3D authentication failed',
			];
		}

		$merchantOrderId = $this->getMerchantOrderId() ?? $this->getTransactionId() ?? '';
		$amount = Helper::formatAmount($this->getAmountInteger());
		$currencyCode = CurrencyCode::fromAlpha($this->getCurrency() ?? 'TRY');
		$installment = $this->getInstallment() ?? 0;

		$hash = Helper::hashNon3D(
			$this->getMerchantId(),
			(string)$merchantOrderId,
			(string)$amount,
			$this->getUserName(),
			$this->getPassword(),
		);

		$data = [
			'APIVersion'          => '',
			'MerchantId'          => $this->getMerchantId(),
			'CustomerId'          => $this->getCustomerId(),
			'UserName'            => $this->getUserName(),
			'TransactionType'     => 'Sale',
			'InstallmentCount'    => (string)$installment,
			'Amount'              => (string)$amount,
			'CurrencyCode'        => $currencyCode,
			'FECCurrencyCode'     => $currencyCode,
			'MerchantOrderId'     => (string)$merchantOrderId,
			'TransactionSecurity' => '3',
			'PaymentType'         => '1',
			'AdditionalData'      => [
				'AdditionalDataList' => [
					'VPosAdditionalData' => [
						'Key'  => 'MD',
						'Data' => $this->getMd() ?? '',
					],
				],
			],
			'HashData'            => $hash,
			'_authSuccess'        => true,
		];

		return $data;
	}

	public function sendData($data)
	{
		// If 3D auth failed, return failure response without sending provision request
		if (empty($data['_authSuccess'])) {
			return $this->response = new CompletePurchaseResponse($this, $data, null);
		}

		// Remove internal keys before sending
		$sendData = $data;
		unset($sendData['_authSuccess']);

		$xml = Helper::arrayToXml($sendData);

		$httpResponse = $this->httpClient->request(
			'POST',
			$this->getEndpoint(),
			[
				'Content-Type' => 'application/xml',
			],
			$xml
		);

		return $this->response = new CompletePurchaseResponse(
			$this,
			$httpResponse->getBody()->getContents(),
			null
		);
	}

	public function getEndpoint(): string
	{
		return $this->prod_endpoint;
	}
}
