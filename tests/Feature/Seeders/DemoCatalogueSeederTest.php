<?php // tests/Feature/Seeders/DemoCatalogueSeederTest.php

use App\Domain\Catalog\Models\Product;
use Database\Seeders\DemoCatalogueSeeder;
use Illuminate\Support\Facades\File;

it('builds the whole catalogue without the source photographs', function () {
    // This is the deployment path. A server has database/demo/card-holders —
    // the finished images, committed — and never has walletImages/, which is
    // 60MB and gitignored. If this needs the originals, a deploy ships an
    // empty shop.
    expect(File::isDirectory(database_path('demo/card-holders')))->toBeTrue();

    (new DemoCatalogueSeeder)->run();

    $products = Product::query()->where('is_active', true)->get();

    expect($products)->toHaveCount(26);

    foreach ($products as $product) {
        expect($product->images)->not->toBeEmpty($product->slug);
    }
});

it('does not re-normalise the fixtures', function () {
    // They are already normalised. Running the normaliser over them again
    // would find the product inside its own tinted sweep, crop to it, and
    // shrink the product a second time — every redeploy making the grid worse.
    $before = filesize(database_path('demo/card-holders/a1.jpg'));

    (new DemoCatalogueSeeder)->run();

    $after = storage_path('app/public/card-holders/a1.jpg');

    expect(File::exists($after))->toBeTrue()
        ->and(filesize($after))->toBe($before);
});

it('leaves shipping and a discount code in place for later', function () {
    (new DemoCatalogueSeeder)->run();

    expect(App\Domain\Shipping\Models\ShippingRate::count())->toBeGreaterThan(0);
});
