<?php

namespace App\Support;

/**
 * Money is stored everywhere as integer paise; these helpers convert at the edges.
 */
class Money
{
    public static function format(int $paise): string
    {
        $sign = $paise < 0 ? '-' : '';
        $paise = abs($paise);

        return $sign.'₹'.self::indianGrouping(intdiv($paise, 100)).'.'.str_pad((string) ($paise % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Plain rupee amount for form inputs, e.g. 99900 => "999.00".
     */
    public static function toRupees(int $paise): string
    {
        return number_format($paise / 100, 2, '.', '');
    }

    /**
     * Parse a validated rupee string such as "999" or "999.5" into paise without float rounding.
     */
    public static function fromRupees(string $rupees): int
    {
        [$whole, $fraction] = array_pad(explode('.', trim($rupees), 2), 2, '');

        return (int) $whole * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    /**
     * Group digits the Indian way: 1234567 => 12,34,567.
     */
    private static function indianGrouping(int $rupees): string
    {
        $digits = (string) $rupees;

        if (strlen($digits) <= 3) {
            return $digits;
        }

        $rest = substr($digits, 0, -3);
        $pairs = array_reverse(array_map('strrev', str_split(strrev($rest), 2)));

        return implode(',', $pairs).','.substr($digits, -3);
    }
}
