<?php // tests/Feature/Storefront/ProductPageTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;

it('shows an active product by slug', function () {
    $product = Product::factory()->create([
        'slug' => 'bifold-wallet', 'name' => 'Bifold wallet', 'is_active' => true,
    ]);
    Variant::factory()->for($product)->create(['is_active' => true, 'stock_quantity' => 3]);

    $this->get('/en/product/bifold-wallet')->assertSuccessful()->assertSee('Bifold wallet');
});

it('404s on an inactive product', function () {
    Product::factory()->create(['slug' => 'hidden', 'is_active' => false]);

    $this->get('/en/product/hidden')->assertNotFound();
});

it('404s on an unknown slug', function () {
    $this->get('/en/product/nope')->assertNotFound();
});

it('shows the story in the active locale', function () {
    $product = Product::factory()->create([
        'slug' => 'bifold-wallet',
        'is_active' => true,
        'story' => ['en' => 'Cut by hand.', 'az' => 'Əl ilə kəsilir.'],
    ]);
    Variant::factory()->for($product)->create(['is_active' => true, 'stock_quantity' => 2]);

    $this->get('/az/product/bifold-wallet')->assertSee('Əl ilə kəsilir.');
});
