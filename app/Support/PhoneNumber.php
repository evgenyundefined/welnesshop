<?php

namespace App\Support;

/**
 * Phones are typed however people type them and stored one way: +7 XXX XXX XX XX.
 * Anything that is not eleven Russian digits is left alone so validation can
 * refuse it with the value the person actually entered.
 */
final readonly class PhoneNumber
{
    public const string PATTERN = '/^\+7 \d{3} \d{3} \d{2} \d{2}$/';

    public static function format(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $raw) ?? '';

        // A local ten-digit number, and the Russian habit of writing 8 for the
        // country code, both mean the same number.
        $digits = match (true) {
            strlen($digits) === 10 => '7'.$digits,
            strlen($digits) === 11 && $digits[0] === '8' => '7'.substr($digits, 1),
            default => $digits,
        };

        if (strlen($digits) !== 11 || $digits[0] !== '7') {
            return trim($raw);
        }

        return sprintf(
            '+7 %s %s %s %s',
            substr($digits, 1, 3),
            substr($digits, 4, 3),
            substr($digits, 7, 2),
            substr($digits, 9, 2),
        );
    }
}
