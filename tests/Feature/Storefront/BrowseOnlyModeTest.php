<?php // tests/Feature/Storefront/BrowseOnlyModeTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;

function browseOnly(): void
{
    config(['shop.ordering' => false]);
}

it('still shows the whole catalogue', function () {
    browseOnly();

    $product = Product::factory()->create(['is_active' => true, 'name' => ['en' => 'Kart qabi', 'az' => 'Kart qabi']]);

    $this->get('/az')->assertOk()->assertSee('Kart qabi');
    $this->get('/az/product/'.$product->slug)->assertOk();
    $this->get('/az?q=kart')->assertOk()->assertSee('Kart qabi');
});

it('makes the bag and checkout unreachable, not merely unlinked', function () {
    // Hiding a button is not a guarantee: the URLs are guessable, and the
    // checkout resolves the payment gateway, which refuses to run outside
    // local/testing and would 500 on a public preview.
    browseOnly();

    $this->get('/az/cart')->assertNotFound();
    $this->get('/az/checkout')->assertNotFound();
    $this->post('/checkout', [])->assertNotFound();
});

it('shows no way to buy, and says why', function () {
    browseOnly();

    $product = Product::factory()->create(['is_active' => true]);
    Variant::factory()->for($product)->create(['is_active' => true, 'stock_quantity' => 5]);

    $home = $this->get('/az');
    $home->assertDontSee(__('shop.collection.quick_add', [], 'az'));
    $home->assertDontSee(__('shop.nav.cart', [], 'az'));

    $this->get('/az/product/'.$product->slug)
        ->assertSee(__('shop.preview.ordering_soon', [], 'az'))
        ->assertDontSee(__('shop.product.add_to_cart', [], 'az'));
});

it('leaves ordering on by default', function () {
    // The flag is opt-out. A missing environment variable must not quietly
    // close the shop.
    expect(config('shop.ordering'))->toBeTrue();

    $this->get('/az/cart')->assertOk();
});
