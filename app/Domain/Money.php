<?php // app/Domain/Money.php

namespace App\Domain;

final class Money
{
    public static function format(int $minor, string $currency = 'AZN'): string
    {
        return number_format($minor / 100, 2, '.', '') . ' ' . $currency;
    }

    public static function percentOf(int $minor, int $percent): int
    {
        $numerator = $minor * $percent;

        return intdiv($numerator, 100) + (($numerator % 100) >= 50 ? 1 : 0);
    }
}
