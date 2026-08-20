<?php // tests/Feature/Storefront/CatalogueOrderTest.php

use App\Domain\Catalog\CatalogueFilter;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\ProductCategory;
use Database\Seeders\WalletCatalogue;
use Illuminate\Support\Str;

// The default sort used to be the id order — the order rows happened to be
// inserted. Everything seeded on the first run looked hand-ordered because the
// insertions followed WalletCatalogue.php top to bottom; but a piece added to
// that list afterwards went in last and landed at the end of the grid, however
// far up the list it had been placed. Four card holders inserted beside the
// card cases came out behind the document holders.
it('puts a newly added product where the catalogue puts it, not last', function () {
    $existing = Product::factory()->create([
        'sort_order' => 20,
        'is_active' => true,
        'category' => ProductCategory::Card,
    ]);

    // Created second, so its id is higher — the thing the old sort keyed on.
    $inserted = Product::factory()->create([
        'sort_order' => 10,
        'is_active' => true,
        'category' => ProductCategory::Card,
    ]);

    $order = (new CatalogueFilter)->apply(Product::query())->pluck('id')->all();

    expect(array_search($inserted->id, $order, true))
        ->toBeLessThan(array_search($existing->id, $order, true));
});

// Seeding a fresh database inserts in catalogue order, so id order and file
// order agree and everything looks fine — which is exactly why this went
// unnoticed. Deleting a product and reseeding recreates it with the highest id
// in the table, which is the state a shop is in after any new batch of
// photographs: the piece belongs in the middle of the grid but was inserted
// last.
function reseedWithOneProductAddedLast(string $englishName): void
{
    (new Database\Seeders\DemoCatalogueSeeder)->run();

    Product::query()->where('slug', Str::slug($englishName))->delete();

    (new Database\Seeders\DemoCatalogueSeeder)->run();
}

function catalogueOrder(): array
{
    return (new CatalogueFilter)->apply(Product::query())
        ->get()
        ->map(fn (Product $p) => $p->getTranslations('name')['en'])
        ->all();
}

it('keeps a piece and its other colours adjacent after it is re-added', function () {
    reseedWithOneProductAddedLast('Signature Card Holder — Walnut');

    $names = catalogueOrder();

    $signature = array_values(array_filter(
        array_keys($names),
        fn ($i) => Str::startsWith($names[$i], 'Signature Card Holder'),
    ));

    expect($signature)->toHaveCount(2)
        ->and($signature[1] - $signature[0])->toBe(1);
});

it('follows the catalogue file from top to bottom, whatever order rows were made in', function () {
    reseedWithOneProductAddedLast('Flap Card Case — Walnut');

    $expected = collect(WalletCatalogue::all())
        ->map(fn ($d) => $d['name']['en'])
        ->values()
        ->all();

    expect(catalogueOrder())->toBe($expected);
});
