<?php

namespace Omnipay\VakifKatilim\Tests\Feature;

use Omnipay\Common\Exception\InvalidCreditCardException;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\VakifKatilim\Constants\CurrencyCode;
use Omnipay\VakifKatilim\Helpers\Helper;
use Omnipay\VakifKatilim\Message\PurchaseRequest;
use Omnipay\VakifKatilim\Message\PurchaseResponse;
use Omnipay\VakifKatilim\Tests\TestCase;

class PurchaseTest extends TestCase
{
    /**
     * @throws InvalidRequestException
     * @throws InvalidCreditCardException
     * @throws \JsonException
     */
    public function test_3d_purchase_request()
    {
        $options = file_get_contents(__DIR__ . '/../Mock/PurchaseRequest3D.json');

        $options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

        $request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize($options);

        $data = $request->getData();

        $this->assertIsArray($data);

        // Verify API version
        $this->assertEquals('1.0.0', $data['APIVersion']);

        // Verify card info
        $this->assertEquals('4111111111111111', $data['CardNumber']);
        $this->assertEquals('99', $data['CardExpireDateYear']);
        $this->assertEquals('12', $data['CardExpireDateMonth']);
        $this->assertEquals('000', $data['CardCVV2']);
        $this->assertEquals('Example User', $data['CardHolderName']);

        // Verify transaction info
        $this->assertEquals('0', $data['InstallmentCount']);
        $this->assertEquals('1234', $data['Amount']);
        $this->assertEquals('1234', $data['DisplayAmount']);
        $this->assertEquals('0949', $data['CurrencyCode']);
        $this->assertEquals('0949', $data['FECCurrencyCode']);
        $this->assertEquals('VK-ORDER-001', $data['MerchantOrderId']);
        $this->assertEquals('3', $data['TransactionSecurity']);
        $this->assertEquals('1', $data['PaymentType']);

        // Verify 3D URLs
        $this->assertEquals('https://example.com/success', $data['OkUrl']);
        $this->assertEquals('https://example.com/failure', $data['FailUrl']);

        // Verify merchant info
        $this->assertEquals('apiuser', $data['UserName']);
        $this->assertEquals('1', $data['MerchantId']);
        $this->assertEquals('400235', $data['CustomerId']);

        // Verify HashPassword field is present (3D specific)
        $this->assertNotEmpty($data['HashPassword']);
        $this->assertEquals(Helper::hashSha1Base64('Api123'), $data['HashPassword']);

        // Verify hash is present and is a base64 string
        $this->assertNotEmpty($data['HashData']);

        // Verify hash calculation
        $hashPassword = Helper::hashSha1Base64('Api123');
        $expectedHash = Helper::hash3D(
            '1',
            'VK-ORDER-001',
            '1234',
            'https://example.com/success',
            'https://example.com/failure',
            'apiuser',
            $hashPassword,
        );
        $this->assertEquals($expectedHash, $data['HashData']);
    }

    /**
     * @throws InvalidRequestException
     * @throws InvalidCreditCardException
     * @throws \JsonException
     */
    public function test_non3d_purchase_request()
    {
        $options = file_get_contents(__DIR__ . '/../Mock/PurchaseRequestNon3D.json');

        $options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

        $request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize($options);

        $data = $request->getData();

        $this->assertIsArray($data);

        // Verify transaction security is 1 for non-3D
        $this->assertEquals('1', $data['TransactionSecurity']);

        // Verify PaymentType
        $this->assertEquals('1', $data['PaymentType']);

        // Verify FECCurrencyCode is present
        $this->assertEquals('0949', $data['FECCurrencyCode']);

        // Verify no OkUrl/FailUrl for non-3D
        $this->assertArrayNotHasKey('OkUrl', $data);
        $this->assertArrayNotHasKey('FailUrl', $data);

        // Verify no HashPassword for non-3D
        $this->assertArrayNotHasKey('HashPassword', $data);

        // Verify hash is non-3D
        $expectedHash = Helper::hashNon3D(
            '1',
            'VK-ORDER-002',
            '1234',
            'apiuser',
            'Api123',
        );
        $this->assertEquals($expectedHash, $data['HashData']);
    }

    public function test_purchase_request_validation_error()
    {
        $options = file_get_contents(__DIR__ . '/../Mock/PurchaseRequest-ValidationError.json');

        $options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

        $request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize($options);

        $this->expectException(InvalidRequestException::class);

        $request->getData();
    }

    /**
     * @throws InvalidRequestException
     * @throws InvalidCreditCardException
     * @throws \JsonException
     */
    public function test_3d_purchase_response_is_redirect()
    {
        $options = file_get_contents(__DIR__ . '/../Mock/PurchaseRequest3D.json');

        $options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

        $request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

        /** @var PurchaseResponse $response */
        $response = $request->initialize($options)->send();

        $this->assertFalse($response->isSuccessful());

        $this->assertTrue($response->isRedirect());

        $this->assertEquals('POST', $response->getRedirectMethod());

        $this->assertEquals(
            'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelPayGate',
            $response->getRedirectUrl()
        );

        $redirectData = $response->getRedirectData();

        $this->assertIsArray($redirectData);

        $this->assertEquals($request->getData(), $redirectData);
    }

    public function test_non3d_purchase_sends_http_request_success()
    {
        $options = file_get_contents(__DIR__ . '/../Mock/PurchaseRequestNon3D.json');

        $options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

        $this->setMockHttpResponse('PurchaseResponseNon3DSuccess.txt');

        $response = $this->gateway->purchase($options)->send();

        $this->assertTrue($response->isSuccessful());

        $this->assertFalse($response->isRedirect());

        $this->assertEquals('00', $response->getCode());

        $this->assertEquals('Onaylandi', $response->getMessage());

        // Verify the HTTP request was sent
        $requests = $this->getMockedRequests();
        $this->assertCount(1, $requests);

        $httpRequest = $requests[0];
        $this->assertEquals('POST', $httpRequest->getMethod());
        $this->assertStringContainsString(
            'boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/Non3DPayGate',
            (string) $httpRequest->getUri()
        );

        // Verify the body is XML with VPosMessageContract root
        $body = (string) $httpRequest->getBody();
        $this->assertStringContainsString('VPosMessageContract', $body);
        $this->assertStringContainsString('<MerchantId>1</MerchantId>', $body);
        $this->assertStringContainsString('<MerchantOrderId>VK-ORDER-002</MerchantOrderId>', $body);
        $this->assertStringContainsString('<TransactionSecurity>1</TransactionSecurity>', $body);
        $this->assertStringContainsString('<PaymentType>1</PaymentType>', $body);
        $this->assertStringContainsString('<FECCurrencyCode>0949</FECCurrencyCode>', $body);
    }

    public function test_non3d_purchase_sends_http_request_error()
    {
        $options = file_get_contents(__DIR__ . '/../Mock/PurchaseRequestNon3D.json');

        $options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

        $this->setMockHttpResponse('PurchaseResponseNon3DError.txt');

        $response = $this->gateway->purchase($options)->send();

        $this->assertFalse($response->isSuccessful());

        $this->assertFalse($response->isRedirect());

        $this->assertEquals('51', $response->getCode());

        $this->assertEquals('Yetersiz bakiye', $response->getMessage());
    }

    public function test_purchase_gateway_method()
    {
        $request = $this->gateway->purchase([
            'merchantId' => '1',
            'customerId' => '400235',
            'userName' => 'apiuser',
            'password' => 'Api123',
        ]);

        $this->assertInstanceOf(PurchaseRequest::class, $request);
    }

    public function test_currency_codes()
    {
        $this->assertEquals('0949', CurrencyCode::fromAlpha('TRY'));
        $this->assertEquals('0840', CurrencyCode::fromAlpha('USD'));
        $this->assertEquals('0978', CurrencyCode::fromAlpha('EUR'));
        $this->assertEquals('0826', CurrencyCode::fromAlpha('GBP'));

        // Unknown currency defaults to TRY
        $this->assertEquals('0949', CurrencyCode::fromAlpha('UNKNOWN'));
    }

    /**
     * @throws InvalidRequestException
     * @throws InvalidCreditCardException
     * @throws \JsonException
     */
    public function test_purchase_with_installment()
    {
        $options = file_get_contents(__DIR__ . '/../Mock/PurchaseRequest3D.json');

        $options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

        $options['installment'] = 3;

        $request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize($options);

        $data = $request->getData();

        $this->assertEquals('3', $data['InstallmentCount']);
    }

    /**
     * @throws InvalidRequestException
     * @throws InvalidCreditCardException
     * @throws \JsonException
     */
    public function test_purchase_with_usd_currency()
    {
        $options = file_get_contents(__DIR__ . '/../Mock/PurchaseRequest3D.json');

        $options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

        $options['currency'] = 'USD';

        $request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize($options);

        $data = $request->getData();

        $this->assertEquals('0840', $data['CurrencyCode']);
        $this->assertEquals('0840', $data['FECCurrencyCode']);
    }
}
