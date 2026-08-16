<?php // app/Http/Controllers/Storefront/ProductController.php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\BespokeCta;
use App\Domain\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ProductController extends Controller
{
    /** $locale comes first: it is the route group's prefix parameter. */
    public function __invoke(string $locale, string $slug): View
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with(['variants', 'images' => fn ($q) => $q->orderBy('sort_order'), 'personalizationOptions'])
            ->firstOrFail();

        // Three related tiles, excluding the current product. Same eager
        // loads as the collection grid — <x-product-tile>'s canQuickAdd()
        // check reads variants/personalizationOptions in memory.
        $related = Product::query()
            ->where('is_active', true)
            ->where('id', '!=', $product->id)
            ->with([
                'images' => fn ($q) => $q->orderBy('sort_order'),
                'variants',
                'personalizationOptions',
            ])
            ->orderBy('id')
            ->limit(3)
            ->get();

        return view('storefront.product', [
            'product' => $product,
            'related' => $related,
            'bespokeCtaHref' => BespokeCta::href(),
        ]);
    }
}
