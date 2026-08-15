<?php // app/Http/Controllers/Storefront/CatalogueController.php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class CatalogueController extends Controller
{
    public function __invoke(): View
    {
        $products = Product::query()
            ->where('is_active', true)
            ->with(['images' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('id')
            ->get();

        return view('storefront.catalogue', ['products' => $products]);
    }
}
