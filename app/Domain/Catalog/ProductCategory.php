<?php // app/Domain/Catalog/ProductCategory.php

namespace App\Domain\Catalog;

/**
 * The collection's category tabs (Cüzdan / Kart qabı). Kept as an enum
 * rather than a loose string so the values are declared once, the same way
 * App\Domain\Order\OrderStatus backs orders.status.
 */
enum ProductCategory: string
{
    case Wallet = 'wallet';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            self::Wallet => 'Cüzdan',
            self::Card => 'Kart qabı',
        };
    }
}
