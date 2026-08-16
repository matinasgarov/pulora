<?php // app/Domain/Catalog/ProductTag.php

namespace App\Domain\Catalog;

/**
 * The tile badge ("Yeni" / "Az qalıb"). Operator-set, not derived from
 * variants.stock_quantity — that column is a production capacity cap the
 * operator sets by hand for a different purpose, and deriving a
 * merchandising label from it would make the label lie about the number.
 * See ProductResource::form()'s `tag` field for the same note at the point
 * an operator would be tempted to wire it up automatically.
 */
enum ProductTag: string
{
    case New = 'new';
    case LowStock = 'low_stock';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Yeni',
            self::LowStock => 'Az qalıb',
        };
    }
}
