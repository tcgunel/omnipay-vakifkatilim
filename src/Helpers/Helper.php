<?php

namespace Omnipay\VakifKatilim\Helpers;

use SimpleXMLElement;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class Helper
{
    /**
     * Generate SHA1 hash and return as Base64.
     */
    public static function hashSha1Base64(string $data): string
    {
        $hashedData = sha1($data, true);

        return base64_encode($hashedData);
    }

    /**
     * Generate hash for Non-3D transactions.
     * SHA1Base64(MerchantId + MerchantOrderId + Amount + UserName + SHA1Base64(Password))
     */
    public static function hashNon3D(
        string $merchantId,
        string $merchantOrderId,
        string $amount,
        string $userName,
        string $password
    ): string {
        $hashedPassword = self::hashSha1Base64($password);

        return self::hashSha1Base64(
            $merchantId . $merchantOrderId . $amount . $userName . $hashedPassword
        );
    }

    /**
     * Generate hash for 3D transactions.
     * SHA1Base64(MerchantId + MerchantOrderId + Amount + OkUrl + FailUrl + UserName + HashPassword)
     */
    public static function hash3D(
        string $merchantId,
        string $merchantOrderId,
        string $amount,
        string $okUrl,
        string $failUrl,
        string $userName,
        string $hashPassword
    ): string {
        return self::hashSha1Base64(
            $merchantId . $merchantOrderId . $amount . $okUrl . $failUrl . $userName . $hashPassword
        );
    }

    /**
     * Build XML string from array data for VakifKatilim VPos.
     */
    public static function arrayToXml(array $data, string $rootElement = 'VPosMessageContract'): string
    {
        $xml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><{$rootElement}/>");

        self::addArrayToXml($xml, $data);

        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;

        return $dom->saveXML();
    }

    /**
     * Recursively add array elements to a SimpleXMLElement.
     */
    private static function addArrayToXml(SimpleXMLElement $xml, array $data): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $child = $xml->addChild($key);
                self::addArrayToXml($child, $value);
            } else {
                $xml->addChild($key, htmlspecialchars((string) $value));
            }
        }
    }

    /**
     * Parse XML string to stdClass object.
     */
    public static function xmlStringToObject(string $data): object
    {
        $encoder = new XmlEncoder();
        $xml = $encoder->decode($data, 'xml');

        return (object) json_decode(json_encode($xml, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Format amount for VakifKatilim: multiply by 100, no dots/commas.
     * e.g. 1.00 TRY = "100", 12.34 TRY = "1234"
     */
    public static function formatAmount(int $amountInteger): string
    {
        return (string) $amountInteger;
    }
}
