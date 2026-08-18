<?php // app/Http/Controllers/Storefront/ProductController.php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\BespokeCta;
use App\Domain\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
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
        //
        // Ranked rather than just "the first three": ordering by id alone
        // showed every visitor the same three dopp kits, on a card case page
        // as readily as on a bag one. The same piece in another colour is the
        // most useful thing to offer, then anything from the same category.
        //
        // The family is the name up to the em dash, slugged. Taken from the
        // English name because the slug is built from it and so does not move
        // with the locale. Splitting the slug on its last hyphen instead looks
        // equivalent and is not: "Document Holder — Black Python" slugs to
        // document-holder-black-python, whose last segment is "python", and the
        // family would come out as document-holder-black — matching nothing.
        $english = $product->getTranslations('name')['en'] ?? $product->name;
        $family = Str::slug(trim(Str::before($english, '—')));

        $related = Product::query()
            ->where('is_active', true)
            ->where('id', '!=', $product->id)
            ->with([
                'images' => fn ($q) => $q->orderBy('sort_order'),
                'variants',
                'personalizationOptions',
            ])
            ->orderByRaw('CASE WHEN slug LIKE ? THEN 0 WHEN category = ? THEN 1 ELSE 2 END', [
                $family.'-%',
                $product->category?->value,
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
