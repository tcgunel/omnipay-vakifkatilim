<?php

namespace Omnipay\VakifKatilim\Message;

use Omnipay\Common\Exception\InvalidCreditCardException;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\VakifKatilim\Constants\CurrencyCode;
use Omnipay\VakifKatilim\Helpers\Helper;
use Omnipay\VakifKatilim\Traits\PurchaseGettersSetters;

class PurchaseRequest extends AbstractRequest
{
    use PurchaseGettersSetters;

    protected $prod_endpoint_non3d = 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/Non3DPayGate';

    protected $prod_endpoint_3d = 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelPayGate';

    /**
     * @throws InvalidRequestException
     * @throws InvalidCreditCardException
     */
    public function getData()
    {
        $this->validate(
            'merchantId',
            'customerId',
            'userName',
            'password',
            'amount',
            'currency',
            'transactionId',
            'card',
        );

        $this->getCard()->validate();

        $amount = Helper::formatAmount($this->getAmountInteger());
        $currencyCode = CurrencyCode::fromAlpha($this->getCurrency());
        $installment = $this->getInstallment() ?? 0;
        $secure = $this->getSecure();

        if ($secure) {
            $this->validate('returnUrl', 'cancelUrl');

            $hashPassword = Helper::hashSha1Base64($this->getPassword());

            $hash = Helper::hash3D(
                $this->getMerchantId(),
                $this->getTransactionId(),
                $amount,
                $this->getReturnUrl(),
                $this->getCancelUrl(),
                $this->getUserName(),
                $hashPassword,
            );

            $data = [
                'OkUrl' => $this->getReturnUrl(),
                'FailUrl' => $this->getCancelUrl(),
                'MerchantId' => $this->getMerchantId(),
                'CustomerId' => $this->getCustomerId(),
                'UserName' => $this->getUserName(),
                'HashPassword' => $hashPassword,
                'MerchantOrderId' => $this->getTransactionId(),
                'InstallmentCount' => (string) $installment,
                'Amount' => $amount,
                'DisplayAmount' => $amount,
                'APIVersion' => '1.0.0',
                'CardNumber' => $this->getCard()->getNumber(),
                'CardExpireDateYear' => substr($this->getCard()->getExpiryYear(), -2),
                'CardExpireDateMonth' => str_pad($this->getCard()->getExpiryMonth(), 2, '0', STR_PAD_LEFT),
                'CardCVV2' => $this->getCard()->getCvv(),
                'CardHolderName' => $this->getCard()->getName(),
                'PaymentType' => '1',
                'CurrencyCode' => $currencyCode,
                'FECCurrencyCode' => $currencyCode,
                'TransactionSecurity' => '3',
                'HashData' => $hash,
            ];
        } else {
            $hash = Helper::hashNon3D(
                $this->getMerchantId(),
                $this->getTransactionId(),
                $amount,
                $this->getUserName(),
                $this->getPassword(),
            );

            $data = [
                'MerchantId' => $this->getMerchantId(),
                'CustomerId' => $this->getCustomerId(),
                'UserName' => $this->getUserName(),
                'CustomerIPAddress' => $this->getClientIp() ?? '127.0.0.1',
                'MerchantOrderId' => $this->getTransactionId(),
                'InstallmentCount' => (string) $installment,
                'Amount' => $amount,
                'DisplayAmount' => $amount,
                'CurrencyCode' => $currencyCode,
                'FECCurrencyCode' => $currencyCode,
                'CardNumber' => $this->getCard()->getNumber(),
                'CardExpireDateYear' => substr($this->getCard()->getExpiryYear(), -2),
                'CardExpireDateMonth' => str_pad($this->getCard()->getExpiryMonth(), 2, '0', STR_PAD_LEFT),
                'CardCVV2' => $this->getCard()->getCvv(),
                'CardHolderName' => $this->getCard()->getName(),
                'PaymentType' => '1',
                'TransactionSecurity' => '1',
                'HashData' => $hash,
            ];
        }

        return $data;
    }

    public function sendData($data)
    {
        if ($this->getSecure()) {
            return $this->response = new PurchaseResponse($this, $data);
        }

        $xml = Helper::arrayToXml($data);

        $httpResponse = $this->httpClient->request(
            'POST',
            $this->getEndpoint(),
            [
                'Content-Type' => 'application/xml',
            ],
            $xml
        );

        return $this->response = new PurchaseResponse($this, $httpResponse->getBody()->getContents(), false);
    }

    public function getEndpoint(): string
    {
        $secure = $this->getSecure();

        return $secure ? $this->prod_endpoint_3d : $this->prod_endpoint_non3d;
    }
}
