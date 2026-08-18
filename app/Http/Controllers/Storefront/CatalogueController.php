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
        $filter = CatalogueFilter::fromRequest($request);

        // Narrowed, ordered and paged in the database, so a request loads the
        // twelve products it renders rather than the whole catalogue. The
        // eager loads are per page for the same reason: canQuickAdd() reads
        // variants and personalizationOptions per tile, and without them that
        // is a query per tile.
        $paginated = $filter
            ->apply(Product::query()->where('is_active', true))
            ->with([
                'images' => fn ($q) => $q->orderBy('sort_order'),
                'variants',
                'personalizationOptions',
            ])
            ->paginate(self::PER_PAGE)
            ->withPath(route('storefront.catalogue', absolute: false))
            ->fragment('shop')
            ->appends($filter->toQuery());

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
            // Distinguishes "the shop is empty" from "your search found
            // nothing", which need different sentences. A count, not a fetch.
            'totalProductCount' => Product::query()->where('is_active', true)->count(),
            'filter' => $filter,
            'bespokeStartingPriceMinor' => self::BESPOKE_STARTING_PRICE_MINOR,
            'bespokeCtaHref' => BespokeCta::href(),
            'heroPoster' => $hero->poster(),
            'heroVideoSources' => $hero->videoSources(),
            'bespokePoster' => app(PublicMedia::class)->image('bespoke'),
        ]);
    }
}
