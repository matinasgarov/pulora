<?php // app/Http/Controllers/Storefront/CatalogueController.php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\BespokeCta;
use App\Domain\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class CatalogueController extends Controller
{
    /**
     * A real AZN starting price for the bespoke fact grid — the prototype's
     * $220 was placeholder filler; this shop trades in AZN only. Above the
     * catalogue's most expensive off-the-shelf piece (12900 qəpik), since a
     * made-to-order commission starts higher than a stocked one.
     */
    private const BESPOKE_STARTING_PRICE_MINOR = 25000;

    public function __invoke(): View
    {
        $products = Product::query()
            ->where('is_active', true)
            ->with([
                'images' => fn ($q) => $q->orderBy('sort_order'),
                // canQuickAdd() reads these in memory per tile — eager-loading
                // here keeps that a fixed number of queries regardless of grid size.
                'variants',
                'personalizationOptions',
            ])
            ->orderBy('id')
            ->get();

        // The bespoke CTA points at a real product page until Phase 3 builds
        // the configurator (see the design plan, Task 4 and Task 5). Shared
        // with the product page's own bespoke CTA via BespokeCta::href() so
        // both point at the same place.
        return view('storefront.catalogue', [
            'products' => $products,
            'bespokeStartingPriceMinor' => self::BESPOKE_STARTING_PRICE_MINOR,
            'bespokeCtaHref' => BespokeCta::href(),
        ]);
    }
}
