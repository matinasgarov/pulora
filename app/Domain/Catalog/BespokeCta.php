<?php // app/Domain/Catalog/BespokeCta.php

namespace App\Domain\Catalog;

use App\Domain\Catalog\Models\Product;

/**
 * The bespoke configurator doesn't exist until Phase 3 (see the design
 * plan's Task 4 and Task 5). Until then, every "commission a piece" CTA —
 * the homepage's bespoke section and the product page's secondary CTA —
 * points at the same place: the first active product's page, falling back
 * to the collection anchor on an empty catalogue. This is the one place
 * that decision is made, so the homepage and every product page agree.
 */
class BespokeCta
{
    public static function href(): string
    {
        $slug = Product::query()->where('is_active', true)->orderBy('id')->value('slug');

        return $slug !== null
            ? route('storefront.product', ['slug' => $slug], absolute: false)
            : route('storefront.catalogue', absolute: false).'#shop';
    }
}
