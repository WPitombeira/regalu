<?php

namespace App\Support;

final class WishlistPrice
{
    public const CURRENCIES = ['BRL', 'USD', 'EUR'];

    public const AMOUNT_PATTERN = '/\A(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?\z/';

    public static function normalize(mixed $amount): ?string
    {
        if (! is_string($amount) && ! is_int($amount) && ! is_float($amount)) {
            return null;
        }

        $amount = (string) $amount;
        if (! preg_match(self::AMOUNT_PATTERN, $amount)) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return $whole.'.'.str_pad($fraction, 2, '0');
    }
}
