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

it('announces made-to-order in the visitor’s language', function () {
    $this->get('/az')->assertSee('Hər parça Bakıda sifarişlə hazırlanır');
});

it('links the wordmark to the catalogue in the current locale', function () {
    $this->get('/az')->assertSee('href="/az"', escape: false);
});
