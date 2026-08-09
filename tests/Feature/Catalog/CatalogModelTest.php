<?php // tests/Feature/Catalog/CatalogModelTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;

it('falls back to the product base price when the variant has no override', function () {
    $product = Product::factory()->create(['base_price_minor' => 8900]);
    $variant = Variant::factory()->for($product)->create(['price_minor_override' => null]);

    expect($variant->effectivePriceMinor())->toBe(8900);
});

it('prefers the variant override when present', function () {
    $product = Product::factory()->create(['base_price_minor' => 8900]);
    $variant = Variant::factory()->for($product)->create(['price_minor_override' => 9900]);

    expect($variant->effectivePriceMinor())->toBe(9900);
});

it('excludes inactive products from the active scope', function () {
    Product::factory()->create(['is_active' => true]);
    Product::factory()->create(['is_active' => false]);

    expect(Product::active()->count())->toBe(1);
});
