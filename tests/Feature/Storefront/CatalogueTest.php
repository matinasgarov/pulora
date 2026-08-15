<?php // tests/Feature/Storefront/CatalogueTest.php

use App\Domain\Catalog\Models\Product;

it('lists active products', function () {
    Product::factory()->create(['name' => 'Bifold wallet', 'is_active' => true]);

    $this->get('/en')->assertSuccessful()->assertSee('Bifold wallet');
});

it('hides inactive products', function () {
    Product::factory()->create(['name' => 'Secret prototype', 'is_active' => false]);

    $this->get('/en')->assertDontSee('Secret prototype');
});

it('shows the price in the grid', function () {
    Product::factory()->create(['base_price_minor' => 4999, 'is_active' => true]);

    $this->get('/en')->assertSee(App\Domain\Money::format(4999));
});

it('shows the product name in the active locale', function () {
    Product::factory()->create([
        'name' => ['en' => 'Bifold wallet', 'az' => 'İkiqat pulqabı'],
        'is_active' => true,
    ]);

    $this->get('/az')->assertSee('İkiqat pulqabı')->assertDontSee('Bifold wallet');
});

it('links each tile to its product page', function () {
    Product::factory()->create(['slug' => 'bifold-wallet', 'is_active' => true]);

    $this->get('/en')->assertSee('/en/product/bifold-wallet', escape: false);
});

it('shows a designed empty state rather than a blank page', function () {
    $this->get('/en')->assertSuccessful()->assertSee(__('shop.catalogue.empty'));
});
