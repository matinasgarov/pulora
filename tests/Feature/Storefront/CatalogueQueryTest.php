<?php // tests/Feature/Storefront/CatalogueQueryTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\ProductCategory;
use Illuminate\Support\Facades\DB;

/**
 * Product models hydrated while doing $work.
 *
 * Counting statements would not have caught this: the old code also ran a
 * single SELECT — it just came back with the entire table. What changed is how
 * many rows that SELECT returns, which is what `retrieved` counts.
 */
function productsHydrated(callable $work): int
{
    $hydrated = 0;

    Product::retrieved(function () use (&$hydrated) {
        $hydrated++;
    });

    $work();

    return $hydrated;
}

it('loads a page of the catalogue, not the whole catalogue', function () {
    // The point of the change. Before this, every request fetched every active
    // product with its images, variants and personalization options in order to
    // render twelve of them — so a shop with a thousand pieces did a thousand
    // rows of work per page view.
    Product::factory()->count(40)->create(['is_active' => true]);

    $shown = $this->get('/en')->viewData('products');

    expect($shown->count())->toBe(12)
        ->and($shown->total())->toBe(40);
});

it('does not load more products just because the shop holds more', function () {
    Product::factory()->count(20)->create(['is_active' => true]);
    $small = productsHydrated(fn () => $this->get('/en'));

    Product::factory()->count(60)->create(['is_active' => true]);
    $large = productsHydrated(fn () => $this->get('/en'));

    // A page of twelve, plus the one BespokeCta::href() reads for the CTA link.
    // Under the old code these were 20 and 80 — the whole table, every request.
    expect($small)->toBe(13)
        ->and($large)->toBe(13);
});

it('keeps the search text in step when a product is renamed', function () {
    // The folded column is written on save. If an edit did not refresh it, the
    // catalogue would go on finding a product by a name it no longer has — and
    // stop finding it by the one it does.
    $product = Product::factory()->create([
        'is_active' => true,
        'name' => ['en' => 'Sumqayit sleeve', 'az' => 'Sumqayıt qabı'],
        'category' => ProductCategory::Card->value,
    ]);

    $this->get('/en?q=sumqayit')->assertSee('Sumqayit sleeve');

    $product->update(['name' => ['en' => 'Lerik sleeve', 'az' => 'Lerik qabı']]);

    $this->get('/en?q=lerik')->assertSee('Lerik sleeve');
    $this->get('/en?q=sumqayit')->assertSee(__('shop.collection.no_matches', [], 'en'));
});

it('treats a wildcard as text a customer typed, not as a pattern', function () {
    // "%" reaching LIKE unescaped matches every row, so a search for it would
    // return the whole shop rather than nothing.
    Product::factory()->create(['is_active' => true, 'name' => ['en' => 'Plain piece', 'az' => 'Sadə parça']]);

    $this->get('/en?q=%25')->assertSee(__('shop.collection.no_matches', [], 'en'));
});

it('orders ties the same way on every page', function () {
    // Twenty pieces at one price: without a tiebreak the database may order
    // them differently per query, which drops some products off both pages.
    Product::factory()->count(20)->create(['is_active' => true, 'base_price_minor' => 4900]);

    $first = collect($this->get('/en?sort=price_asc')->viewData('products')->items())->pluck('id');
    $second = collect($this->get('/en?sort=price_asc&page=2')->viewData('products')->items())->pluck('id');

    expect($first->intersect($second))->toBeEmpty()
        ->and($first->merge($second)->unique())->toHaveCount(20);
});
