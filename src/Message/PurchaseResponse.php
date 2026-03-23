<?php

namespace Omnipay\VakifKatilim\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RedirectResponseInterface;
use Omnipay\Common\Message\RequestInterface;
use Omnipay\VakifKatilim\Helpers\Helper;

/**
 * VakifKatilim Purchase Response
 *
 * For 3D secure: redirects to bank page (isRedirect = true, isSuccessful = false)
 * For Non-3D: contains XML response (isSuccessful based on ResponseCode)
 */
class PurchaseResponse extends AbstractResponse implements RedirectResponseInterface
{
	protected bool $is3D;

	protected ?object $parsedResponse = null;

	/**
	 * @param RequestInterface $request
	 * @param mixed $data - array for 3D (redirect data), string for non-3D (XML response)
	 * @param bool $is3D
	 */
	public function __construct(RequestInterface $request, $data, bool $is3D = true)
	{
		parent::__construct($request, $data);

		$this->is3D = $is3D;

		if (!$is3D && is_string($data)) {
			$this->parsedResponse = Helper::xmlStringToObject($data);
		}
	}

	public function isSuccessful(): bool
	{
		if ($this->is3D) {
			return false;
		}

		return isset($this->parsedResponse->ResponseCode)
			&& $this->parsedResponse->ResponseCode === '00';
	}

	public function isRedirect(): bool
	{
		return $this->is3D;
	}

	public function getRedirectUrl()
	{
		if (!$this->is3D) {
			return null;
		}

		/** @var PurchaseRequest $request */
		$request = $this->getRequest();

		return $request->getEndpoint();
	}

	public function getRedirectMethod(): string
	{
		return 'POST';
	}

	public function getRedirectData(): array
	{
		if (!$this->is3D) {
			return [];
		}

		return (array)$this->getData();
	}

	public function getMessage(): ?string
	{
		if ($this->is3D) {
			return null;
		}

		return $this->parsedResponse->ResponseMessage ?? null;
	}

	public function getCode(): ?string
	{
		if ($this->is3D) {
			return null;
		}

		return $this->parsedResponse->ResponseCode ?? null;
	}

	public function getParsedResponse(): ?object
	{
		return $this->parsedResponse;
	}
}
