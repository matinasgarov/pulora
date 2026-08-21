<?php // tests/Feature/Storefront/BespokeCtaTest.php

use App\Domain\Catalog\BespokeCta;
use App\Domain\Catalog\Models\Product;
use Illuminate\Support\Facades\URL;

it('opens WhatsApp while ordering is closed', function () {
    // It used to fall through to the first product in the grid — a dopp kit —
    // so "have this custom-made" on a card case opened a bag.
    config(['shop.ordering' => false]);

    expect(BespokeCta::href())->toBe('https://wa.me/'.config('shop.whatsapp'))
        ->and(BespokeCta::isExternal())->toBeTrue();
});

it('goes back to a catalogue link once ordering is open', function () {
    config(['shop.ordering' => true]);
    URL::defaults(['locale' => 'en']);

    Product::factory()->create(['is_active' => true]);

    expect(BespokeCta::href())->toStartWith('/en/')
        ->and(BespokeCta::isExternal())->toBeFalse();
});

it('renders the WhatsApp link on a product page, opening in a new tab', function () {
    config(['shop.ordering' => false]);

    $product = Product::factory()->create(['is_active' => true]);

    $html = $this->get('/en/product/'.$product->slug)->assertOk()->getContent();

    expect($html)->toContain('https://wa.me/'.config('shop.whatsapp'))
        ->and($html)->toContain('rel="noopener"');
});
