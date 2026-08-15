<?php // app/Http/Controllers/Storefront/ProductController.php

namespace App\Http\Controllers\Storefront;

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

        return view('storefront.product', ['product' => $product]);
    }
}
