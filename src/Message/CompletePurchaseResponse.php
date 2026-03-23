<?php

namespace Omnipay\VakifKatilim\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RequestInterface;
use Omnipay\VakifKatilim\Helpers\Helper;

/**
 * VakifKatilim Complete Purchase Response
 *
 * Handles the provision response after 3D authentication.
 */
class CompletePurchaseResponse extends AbstractResponse
{
    protected ?object $parsedResponse = null;

    protected ?object $authResponse = null;

    /**
     * @param RequestInterface $request
     * @param mixed $data - array (auth failed) or string (XML provision response)
     * @param object|null $authResponse - parsed 3D authentication response
     */
    public function __construct(RequestInterface $request, $data, ?object $authResponse = null)
    {
        parent::__construct($request, $data);

        $this->authResponse = $authResponse;

        if (is_string($data)) {
            $this->parsedResponse = Helper::xmlStringToObject($data);
        }
    }

    public function isSuccessful(): bool
    {
        // If data is an array, it means auth failed
        if (is_array($this->data)) {
            return false;
        }

        return $this->parsedResponse !== null
            && isset($this->parsedResponse->ResponseCode)
            && $this->parsedResponse->ResponseCode === '00';
    }

    public function getMessage(): ?string
    {
        if (is_array($this->data)) {
            return $this->data['ResponseMessage'] ?? '3D authentication failed';
        }

        return $this->parsedResponse->ResponseMessage ?? null;
    }

    public function getCode(): ?string
    {
        if (is_array($this->data)) {
            return $this->data['ResponseCode'] ?? null;
        }

        return $this->parsedResponse->ResponseCode ?? null;
    }

    public function getTransactionReference(): ?string
    {
        if ($this->parsedResponse === null) {
            return null;
        }

        return $this->parsedResponse->MerchantOrderId ?? $this->parsedResponse->OrderId ?? null;
    }

    public function getParsedResponse(): ?object
    {
        return $this->parsedResponse;
    }

    public function getAuthResponse(): ?object
    {
        return $this->authResponse;
    }
}
