<?php

namespace Omnipay\VakifKatilim\Tests\Feature;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\VakifKatilim\Message\CompletePurchaseRequest;
use Omnipay\VakifKatilim\Message\CompletePurchaseResponse;
use Omnipay\VakifKatilim\Tests\TestCase;

class CompletePurchaseTest extends TestCase
{
    /**
     * @throws InvalidRequestException
     * @throws \JsonException
     */
    public function test_complete_purchase_request_with_successful_auth()
    {
        $options = file_get_contents(__DIR__ . '/../Mock/CompletePurchaseRequest.json');

        $options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

        $options['responseCode'] = '00';
        $options['responseMessage'] = 'Kart dogrulandi';
        $options['merchantOrderId'] = 'VK-ORDER-001';
        $options['md'] = '67YtBfBRTZ0XBKnAHi8c/A==';

        $request = new CompletePurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize($options);

        $data = $request->getData();

        $this->assertIsArray($data);

        // Auth should be successful
        $this->assertTrue($data['_authSuccess']);

        // Verify provision request data
        $this->assertEquals('', $data['APIVersion']);
        $this->assertEquals('1', $data['MerchantId']);
        $this->assertEquals('400235', $data['CustomerId']);
        $this->assertEquals('apiuser', $data['UserName']);
        $this->assertEquals('Sale', $data['TransactionType']);
        $this->assertEquals('0', $data['InstallmentCount']);
        $this->assertEquals('1234', $data['Amount']);
        $this->assertEquals('VK-ORDER-001', $data['MerchantOrderId']);
        $this->assertEquals('3', $data['TransactionSecurity']);
        $this->assertEquals('1', $data['PaymentType']);
        $this->assertEquals('0949', $data['CurrencyCode']);
        $this->assertEquals('0949', $data['FECCurrencyCode']);

        // Verify MD data with VakifKatilim nesting
        $this->assertEquals('67YtBfBRTZ0XBKnAHi8c/A==', $data['AdditionalData']['AdditionalDataList']['VPosAdditionalData']['Data']);
        $this->assertEquals('MD', $data['AdditionalData']['AdditionalDataList']['VPosAdditionalData']['Key']);

        // Verify hash is present
        $this->assertNotEmpty($data['HashData']);
    }

    /**
     * @throws InvalidRequestException
     * @throws \JsonException
     */
    public function test_complete_purchase_request_with_failed_auth()
    {
        $options = file_get_contents(__DIR__ . '/../Mock/CompletePurchaseRequest.json');

        $options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

        $options['responseCode'] = 'HashDataError';
        $options['responseMessage'] = 'Guvenlik Hatasi - Loss Cevap';

        $request = new CompletePurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize($options);

        $data = $request->getData();

        $this->assertIsArray($data);

        // Auth should be failed
        $this->assertFalse($data['_authSuccess']);

        $this->assertEquals('HashDataError', $data['ResponseCode']);
        $this->assertEquals('Guvenlik Hatasi - Loss Cevap', $data['ResponseMessage']);
    }

    public function test_complete_purchase_response_provision_success()
    {
        $httpResponse = $this->getMockHttpResponse('CompletePurchaseProvisionSuccess.txt');

        $response = new CompletePurchaseResponse(
            $this->getMockRequest(),
            $httpResponse->getBody()->getContents(),
            null
        );

        $this->assertTrue($response->isSuccessful());

        $this->assertEquals('00', $response->getCode());

        $this->assertEquals('Onaylandi', $response->getMessage());

        $this->assertEquals('VK-ORDER-001', $response->getTransactionReference());
    }

    public function test_complete_purchase_response_provision_error()
    {
        $httpResponse = $this->getMockHttpResponse('CompletePurchaseProvisionError.txt');

        $response = new CompletePurchaseResponse(
            $this->getMockRequest(),
            $httpResponse->getBody()->getContents(),
            null
        );

        $this->assertFalse($response->isSuccessful());

        $this->assertEquals('EmptyMDException', $response->getCode());

        $this->assertEquals('MD degeri bos', $response->getMessage());
    }

    public function test_complete_purchase_response_auth_failed()
    {
        $failedData = [
            '_authSuccess' => false,
            'ResponseCode' => 'HashDataError',
            'ResponseMessage' => 'Guvenlik Hatasi - Loss Cevap',
        ];

        $response = new CompletePurchaseResponse(
            $this->getMockRequest(),
            $failedData,
            null
        );

        $this->assertFalse($response->isSuccessful());

        $this->assertEquals('HashDataError', $response->getCode());

        $this->assertEquals('Guvenlik Hatasi - Loss Cevap', $response->getMessage());
    }

    public function test_complete_purchase_sends_provision_request_on_success()
    {
        $options = file_get_contents(__DIR__ . '/../Mock/CompletePurchaseRequest.json');

        $options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

        $options['responseCode'] = '00';
        $options['responseMessage'] = 'Kart dogrulandi';
        $options['merchantOrderId'] = 'VK-ORDER-001';
        $options['md'] = '67YtBfBRTZ0XBKnAHi8c/A==';

        $this->setMockHttpResponse('CompletePurchaseProvisionSuccess.txt');

        $response = $this->gateway->completePurchase($options)->send();

        $this->assertTrue($response->isSuccessful());

        // Verify the HTTP request was sent to provision endpoint
        $requests = $this->getMockedRequests();
        $this->assertCount(1, $requests);

        $httpRequest = $requests[0];
        $this->assertEquals('POST', $httpRequest->getMethod());
        $this->assertStringContainsString(
            'boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelProvisionGate',
            (string) $httpRequest->getUri()
        );

        // Verify the body is XML with VPosMessageContract root
        $body = (string) $httpRequest->getBody();
        $this->assertStringContainsString('VPosMessageContract', $body);
        $this->assertStringContainsString('<MerchantId>1</MerchantId>', $body);
        $this->assertStringContainsString('67YtBfBRTZ0XBKnAHi8c/A==', $body);
        $this->assertStringContainsString('<PaymentType>1</PaymentType>', $body);
    }

    public function test_complete_purchase_does_not_send_provision_on_auth_failure()
    {
        $options = file_get_contents(__DIR__ . '/../Mock/CompletePurchaseRequest.json');

        $options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

        $options['responseCode'] = 'HashDataError';
        $options['responseMessage'] = 'Guvenlik Hatasi';

        $response = $this->gateway->completePurchase($options)->send();

        $this->assertFalse($response->isSuccessful());

        // No HTTP request should be sent
        $requests = $this->getMockedRequests();
        $this->assertCount(0, $requests);
    }

    public function test_complete_purchase_gateway_method()
    {
        $request = $this->gateway->completePurchase([
            'merchantId' => '1',
            'customerId' => '400235',
            'userName' => 'apiuser',
            'password' => 'Api123',
        ]);

        $this->assertInstanceOf(CompletePurchaseRequest::class, $request);
    }

    public function test_complete_purchase_request_validation_error()
    {
        $request = new CompletePurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize([]);

        $this->expectException(InvalidRequestException::class);

        $request->getData();
    }
}
