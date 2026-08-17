<?php // app/Http/Controllers/Storefront/CatalogueController.php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\BespokeCta;
use App\Domain\Catalog\CatalogueFilter;
use App\Domain\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use App\Support\HeroMedia;
use App\Support\PublicMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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

    /** Three full rows of the desktop grid, which is 4 across. */
    private const PER_PAGE = 12;

    public function __invoke(Request $request): View|RedirectResponse
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

        // Paginated after filtering, so a page holds 12 of the matches rather
        // than whatever survives from a page of the whole catalogue. The filter
        // state and #shop ride along on every page link, so paging keeps the
        // grid you are looking at and returns you to it.
        $page = LengthAwarePaginator::resolveCurrentPage();

        $paginated = new LengthAwarePaginator(
            $matches->forPage($page, self::PER_PAGE)->values(),
            $matches->count(),
            self::PER_PAGE,
            $page,
            [
                'path' => route('storefront.catalogue', absolute: false),
                'fragment' => 'shop',
            ],
        );

        $paginated->appends($filter->toQuery());

        // ?page=99 otherwise renders a grid with nothing in it, no pager to
        // escape by, and a toolbar still reporting 15 pieces — a dead end
        // reachable from a stale link or an edited URL.
        if ($paginated->currentPage() > $paginated->lastPage()) {
            return redirect()->to($paginated->url($paginated->lastPage()));
        }

        // The bespoke CTA points at a real product page until Phase 3 builds
        // the configurator (see the design plan, Task 4 and Task 5). Shared
        // with the product page's own bespoke CTA via BespokeCta::href() so
        // both point at the same place.
        $hero = app(HeroMedia::class);

        return view('storefront.catalogue', [
            'products' => $paginated,
            'totalProductCount' => $products->count(),
            'filter' => $filter,
            'bespokeStartingPriceMinor' => self::BESPOKE_STARTING_PRICE_MINOR,
            'bespokeCtaHref' => BespokeCta::href(),
            'heroPoster' => $hero->poster(),
            'heroVideoSources' => $hero->videoSources(),
            'bespokePoster' => app(PublicMedia::class)->image('bespoke'),
        ]);
    }
}
