<?php // app/Domain/Money.php

namespace App\Domain;

final class Money
{
    public static function format(int $minor, string $currency = 'AZN'): string
    {
        return number_format($minor / 100, 2, '.', '') . ' ' . $currency;
    }

    public static function percentOf(int $minor, float $percent): int
    {
        return (int) round($minor * $percent / 100, 0, PHP_ROUND_HALF_UP);
    }
}
