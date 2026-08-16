<?php // tests/Feature/Storefront/ProductPageTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
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

it('shows a breadcrumb that links home and names the product', function () {
    $product = Product::factory()->create(['slug' => 'bifold-wallet', 'name' => 'Bifold wallet', 'is_active' => true]);
    Variant::factory()->for($product)->create(['is_active' => true, 'stock_quantity' => 3]);

    $this->get('/az/product/bifold-wallet')
        ->assertSee(__('shop.nav.catalogue', [], 'az'))
        ->assertSee('href="'.route('storefront.catalogue', ['locale' => 'az']).'"', false)
        ->assertSee('Bifold wallet');
});

it('fills the gallery from real product images in sort order', function () {
    $product = Product::factory()->create(['slug' => 'bifold-wallet', 'is_active' => true]);
    Variant::factory()->for($product)->create(['is_active' => true, 'stock_quantity' => 3]);

    ProductImage::create(['product_id' => $product->id, 'path' => 'a.jpg', 'alt_text' => 'The hero shot', 'sort_order' => 0]);
    ProductImage::create(['product_id' => $product->id, 'path' => 'b.jpg', 'alt_text' => 'The edge paint macro', 'sort_order' => 1]);

    $response = $this->get('/en/product/bifold-wallet');

    $response->assertSee('alt="The hero shot"', false)
        ->assertSee('alt="The edge paint macro"', false);

    // Only 2 of 4 slots have real images — the other two fall back to the
    // placeholder frame, so the layout never collapses.
    $response->assertSee(__('shop.placeholder.gallery.interior', ['name' => $product->name], 'en'))
        ->assertSee(__('shop.placeholder.gallery.in_hand', ['name' => $product->name], 'en'));
});

it('renders all four gallery slots as placeholders when there are no images at all', function () {
    $product = Product::factory()->create(['slug' => 'bifold-wallet', 'is_active' => true]);
    Variant::factory()->for($product)->create(['is_active' => true, 'stock_quantity' => 3]);

    $this->get('/en/product/bifold-wallet')
        ->assertSee(__('shop.placeholder.gallery.hero', ['name' => $product->name], 'en'))
        ->assertSee(__('shop.placeholder.gallery.edge_paint', ['name' => $product->name], 'en'))
        ->assertSee(__('shop.placeholder.gallery.interior', ['name' => $product->name], 'en'))
        ->assertSee(__('shop.placeholder.gallery.in_hand', ['name' => $product->name], 'en'));
});

it('renders the spec table from the specs field', function () {
    $product = Product::factory()->create([
        'slug' => 'bifold-wallet', 'is_active' => true,
        'specs' => ['en' => [['label' => 'Card slots', 'value' => '6']], 'az' => []],
    ]);
    Variant::factory()->for($product)->create(['is_active' => true, 'stock_quantity' => 3]);

    $this->get('/en/product/bifold-wallet')
        ->assertSee('Card slots')
        ->assertSee('6');
});

it('renders nothing for the spec table when specs is empty', function () {
    $product = Product::factory()->create(['slug' => 'bifold-wallet', 'is_active' => true, 'specs' => null]);
    Variant::factory()->for($product)->create(['is_active' => true, 'stock_quantity' => 3]);

    $this->get('/en/product/bifold-wallet')->assertDontSee('data-product-specs', false);
});

it('shows the four trust list lines, identical for every product', function () {
    $product = Product::factory()->create(['slug' => 'bifold-wallet', 'is_active' => true]);
    Variant::factory()->for($product)->create(['is_active' => true, 'stock_quantity' => 3]);

    $response = $this->get('/az/product/bifold-wallet');

    foreach (__('shop.product.trust', [], 'az') as $line) {
        $response->assertSee($line);
    }
});

it('points the bespoke CTA at the same place the homepage bespoke CTA points', function () {
    $product = Product::factory()->create(['slug' => 'bifold-wallet', 'is_active' => true]);
    Variant::factory()->for($product)->create(['is_active' => true, 'stock_quantity' => 3]);

    $this->get('/az/product/bifold-wallet')
        ->assertSee('href="'.\App\Domain\Catalog\BespokeCta::href().'"', false);
});

it('shows the unavailable message and hides the add-to-bag button when capacity is exhausted', function () {
    $product = Product::factory()->create(['slug' => 'bifold-wallet', 'is_active' => true]);
    Variant::factory()->for($product)->create(['is_active' => true, 'stock_quantity' => 0]);

    $this->get('/az/product/bifold-wallet')
        ->assertSee(__('shop.product.unavailable', [], 'az'))
        ->assertDontSee(__('shop.product.add_to_cart', [], 'az'));
});

it('shows three related products, excluding the current one', function () {
    $product = Product::factory()->create(['slug' => 'bifold-wallet', 'name' => 'Bifold wallet', 'is_active' => true]);
    Variant::factory()->for($product)->create(['is_active' => true, 'stock_quantity' => 3]);

    $others = Product::factory()->count(4)->create(['is_active' => true]);
    foreach ($others as $other) {
        Variant::factory()->for($other)->create(['is_active' => true, 'stock_quantity' => 3]);
    }

    $response = $this->get('/az/product/bifold-wallet');

    $response->assertSee(__('shop.product.related', [], 'az'));

    foreach ($others->take(3) as $expected) {
        $response->assertSee($expected->name);
    }

    $response->assertDontSee($others->last()->name);
});
