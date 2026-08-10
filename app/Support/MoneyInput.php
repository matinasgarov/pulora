<?php // app/Support/MoneyInput.php

namespace App\Support;

use Filament\Forms\Components\TextInput;

/**
 * Converts between the manats-with-decimals the operator types and the integer
 * qepik the domain stores. Parsing is done on the string: (int) ($value * 100)
 * silently produces 4998 for "49.99" on some inputs, and a shop that charges a
 * qepik less than it meant to is a shop with a bug nobody notices for a year.
 */
final class MoneyInput
{
    public static function toMinor(?string $manats): ?int
    {
        if ($manats === null || trim($manats) === '') {
            return null;
        }

        $normalized = str_replace([',', ' '], ['.', ''], trim($manats));

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');

        $fraction = str_pad(substr($fraction, 0, 3), 3, '0');

        $thousandths = ((int) $whole) * 1000 + ((int) substr($fraction, 0, 3));

        return intdiv($thousandths, 10) + (($thousandths % 10) >= 5 ? 1 : 0);
    }

    public static function toManats(?int $minor): ?string
    {
        if ($minor === null) {
            return null;
        }

        return number_format($minor / 100, 2, '.', '');
    }

    /**
     * A price field, configured once. Every money input in the panel is built
     * from this so the conversion rule lives in exactly one place.
     */
    public static function field(string $name): TextInput
    {
        return TextInput::make($name)
            ->prefix('AZN')
            ->rule('regex:/^\d+([.,]\d{1,2})?$/')
            ->formatStateUsing(fn (?int $state) => self::toManats($state))
            ->dehydrateStateUsing(fn (?string $state) => self::toMinor($state));
    }
}
