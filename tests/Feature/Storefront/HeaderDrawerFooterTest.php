<?php // tests/Feature/Storefront/HeaderDrawerFooterTest.php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;

it('shows the header nav labels in the active locale', function () {
    $this->get('/az')
        ->assertSee(__('shop.nav.catalogue', locale: 'az'))
        ->assertSee(__('shop.nav.orders', locale: 'az'))
        ->assertSee(__('shop.nav.atelier', locale: 'az'));

    $this->get('/en')
        ->assertSee(__('shop.nav.catalogue', locale: 'en'))
        ->assertSee(__('shop.nav.orders', locale: 'en'))
        ->assertSee(__('shop.nav.atelier', locale: 'en'));
});

it('shows the real cart count as a badge on the bag icon', function () {
    $product = Product::factory()->create(['is_active' => true]);
    $variant = Variant::factory()->for($product)->create(['is_active' => true, 'stock_quantity' => 5]);

    app(CartService::class)->add($variant->id, 2);

    $this->get('/az')->assertSee('>2<', escape: false);
});

it('does not show a bag badge for an empty cart', function () {
    $this->get('/az')->assertDontSee('>0<', escape: false);
});

it('wires the drawer language toggle to the real locale routes', function () {
    $this->get('/en')->assertSee('href="/az"', escape: false);
    $this->get('/az')->assertSee('href="/en"', escape: false);
});

it('labels the drawer language row', function () {
    $this->get('/az')->assertSee(__('shop.nav.language', locale: 'az'));
});

it('renders the search panel with the exact handoff placeholder and a close control', function () {
    $this->get('/az')
        ->assertSee('Məhsul axtarın — cüzdan, kart qabı, cordovan…', escape: false)
        ->assertSee(__('shop.nav.close', locale: 'az'));
});

it('does not gate the nav links behind javascript', function () {
    // The nav links must be present as real anchors in the raw HTML response,
    // not injected by a script, so they work with JS off.
    $response = $this->get('/az');

    $response->assertSee('href="'.route('storefront.catalogue', absolute: false).'"', escape: false);
    $response->assertSee('href="'.route('orders.lookup', absolute: false).'"', escape: false);
});

it('renders the footer headings and legal bar in the active locale', function () {
    $this->get('/az')
        ->assertSee(__('shop.footer.headings.shop', locale: 'az'))
        ->assertSee(__('shop.footer.headings.service', locale: 'az'))
        ->assertSee(__('shop.footer.headings.contact', locale: 'az'))
        ->assertSee(__('shop.footer.legal.copyright', locale: 'az'))
        ->assertSee(__('shop.footer.legal.made_in', locale: 'az'));
});

it('never links to a dead hash target in the footer', function () {
    $this->get('/az')->assertDontSee('href="#"', escape: false);
});

it('points the footer shop link at the real catalogue route', function () {
    $this->get('/az')->assertSee(
        'href="'.route('storefront.catalogue', absolute: false).'"',
        escape: false
    );
});

it('submits the header search to the catalogue without a script', function () {
    // A plain GET form: Enter works with scripting off, and the result is a
    // URL that can be shared. The #shop fragment lands on the grid rather than
    // at the top of the hero, which is where the answer to a search is.
    $this->get('/az')
        ->assertSee('<form method="GET" action="'.route('storefront.catalogue', absolute: false).'"', escape: false)
        ->assertSee('name="q"', escape: false);
});

it('keeps the search box filled in after a search', function () {
    // An empty box beside a filtered grid reads as though the search was lost.
    $this->get('/az?q=cüzdan')->assertSee('value="cüzdan"', escape: false);
});

it('points the footer category links at real filtered catalogue URLs', function () {
    // These were plain text while category filtering was unbuilt. Now that it
    // works, leaving them inert would be the dead-link problem in reverse.
    $this->get('/az')
        ->assertSee('href="'.route('storefront.catalogue', ['category' => 'wallet'], absolute: false).'#shop"', escape: false)
        ->assertSee('href="'.route('storefront.catalogue', ['category' => 'card'], absolute: false).'#shop"', escape: false);
});
