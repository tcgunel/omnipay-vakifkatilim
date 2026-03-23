<?php

namespace Omnipay\VakifKatilim\Constants;

class CurrencyCode
{
    public const TRY = '0949';

    public const USD = '0840';

    public const EUR = '0978';

    public const GBP = '0826';

    public static function fromAlpha(string $currency): string
    {
        $map = [
            'TRY' => self::TRY,
            'USD' => self::USD,
            'EUR' => self::EUR,
            'GBP' => self::GBP,
        ];

        return $map[$currency] ?? self::TRY;
    }
}
