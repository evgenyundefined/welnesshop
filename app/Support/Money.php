<?php

namespace App\Support;

/**
 * The storefront formats money in the browser; an email has no browser, so the
 * same shape is produced here — a thin space between the groups and the sign
 * after the amount, the way a Russian price is written.
 */
final readonly class Money
{
    private const SIGNS = ['RUB' => '₽', 'USD' => '$', 'EUR' => '€'];

    public static function format(int $minor, string $currency): string
    {
        $amount = number_format($minor / 100, $minor % 100 === 0 ? 0 : 2, ',', ' ');

        return $amount.' '.(self::SIGNS[$currency] ?? $currency);
    }
}
