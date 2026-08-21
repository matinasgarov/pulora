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
        // While ordering is closed there is nowhere to send anyone: the
        // configurator does not exist and the bag and checkout return 404. It
        // used to fall through to the first product in the grid, which is a
        // dopp kit — so "have this custom-made" on a card case opened a bag.
        if (! config('shop.ordering')) {
            return 'https://wa.me/'.config('shop.whatsapp');
        }

        $slug = Product::query()->where('is_active', true)->orderBy('id')->value('slug');

        return $slug !== null
            ? route('storefront.product', ['slug' => $slug], absolute: false)
            : route('storefront.catalogue', absolute: false).'#shop';
    }

    /** Whether href() currently points off-site, so links can open in a new tab. */
    public static function isExternal(): bool
    {
        return ! config('shop.ordering');
    }
}
