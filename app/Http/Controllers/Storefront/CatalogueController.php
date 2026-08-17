<?php // app/Http/Controllers/Storefront/CatalogueController.php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\BespokeCta;
use App\Domain\Catalog\CatalogueFilter;
use App\Domain\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use App\Support\HeroMedia;
use Illuminate\Http\Request;
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

    public function __invoke(Request $request): View
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

        // The full active catalogue is fetched either way — the grid shows all
        // of it when nothing is filtered — so search, filter and sort run over
        // the loaded collection and cost no extra queries. See CatalogueFilter.
        $filter = CatalogueFilter::fromRequest($request);
        $matches = $filter->apply($products);

        // The bespoke CTA points at a real product page until Phase 3 builds
        // the configurator (see the design plan, Task 4 and Task 5). Shared
        // with the product page's own bespoke CTA via BespokeCta::href() so
        // both point at the same place.
        $hero = app(HeroMedia::class);

        return view('storefront.catalogue', [
            'products' => $matches,
            'totalProductCount' => $products->count(),
            'filter' => $filter,
            'bespokeStartingPriceMinor' => self::BESPOKE_STARTING_PRICE_MINOR,
            'bespokeCtaHref' => BespokeCta::href(),
            'heroPoster' => $hero->poster(),
            'heroVideoSources' => $hero->videoSources(),
        ]);
    }
}
