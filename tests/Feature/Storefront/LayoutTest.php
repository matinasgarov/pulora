<?php // tests/Feature/Storefront/LayoutTest.php

use App\Domain\Catalog\Models\Product;

it('renders the shell with the brand name', function () {
    $this->get('/en')->assertSuccessful()->assertSee('Pulora');
});

it('offers a link to the other locale', function () {
    $this->get('/en')->assertSuccessful()->assertSee('/az', escape: false);
});

it('renders prices through the shared component', function () {
    Product::factory()->create(['base_price_minor' => 4999, 'is_active' => true]);

    $this->get('/en')->assertSee(App\Domain\Money::format(4999));
});

it('sets the html lang attribute from the locale', function () {
    $this->get('/az')->assertSee('lang="az"', escape: false);
});

it('links the wordmark to the catalogue in the current locale', function () {
    $this->get('/az')->assertSee('href="/az"', escape: false);
});

// The header strip (since removed) said "Bakıda" while the footer two hundred
// pixels below said "Baku" — the same city, one localised and one not, on one
// page. The footer half of that pairing still needs pinning.
it('localises the city in the footer', function () {
    $this->get('/az')->assertSee('Bakı, Səbail');
    $this->get('/en')->assertSee('Baku, Sabail');
});

it('never puts the glass header on a page without a dark hero', function () {
    // The overlay is opt-in per page. A cart or checkout page opens on cream,
    // where light-on-glass would be unreadable.
    $this->get('/az/cart')->assertDontSee('data-overlay', false);
    $this->get('/az/orders')->assertDontSee('data-overlay', false);
});
