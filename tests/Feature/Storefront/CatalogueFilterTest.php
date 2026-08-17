<?php // tests/Feature/Storefront/CatalogueFilterTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\ProductCategory;

/**
 * Fixture names are deliberately unlike anything in the site's own copy.
 * "Card holder" reads as absent from the grid but present in the footer's
 * links, so asserting on it would test the chrome instead of the filter.
 */
function piece(string $en, string $az, int $priceMinor, ProductCategory $category, string $leather): Product
{
    return Product::factory()->create([
        'is_active' => true,
        'name' => ['en' => $en, 'az' => $az],
        'leather' => ['en' => $leather, 'az' => $leather],
        'description' => ['en' => 'Handmade.', 'az' => 'Əl işi.'],
        'base_price_minor' => $priceMinor,
        'category' => $category->value,
    ]);
}

beforeEach(function () {
    piece('Aran bifold', 'Aran ikiqat', 8900, ProductCategory::Wallet, 'Cordovan');
    piece('Xazar sleeve', 'Xəzər qabı', 4900, ProductCategory::Card, 'Buttero');
    piece('Qobu longfold', 'Qobu uzun cüzdan', 12900, ProductCategory::Wallet, 'Buttero');
});

it('searches by name', function () {
    $this->get('/en?q=aran')
        ->assertSee('Aran bifold')
        ->assertDontSee('Xazar sleeve')
        ->assertDontSee('Qobu longfold');
});

it('searches by leather as well as name', function () {
    $this->get('/en?q=cordovan')
        ->assertSee('Aran bifold')
        ->assertDontSee('Xazar sleeve');
});

it('searches in the language being read', function () {
    $this->get('/az?q=xəzər')
        ->assertSee('Xəzər qabı')
        ->assertDontSee('Aran ikiqat');
});

it('finds Azerbaijani words typed without their diacritics', function () {
    // Someone on a keyboard without ü typing "cuzdan" means "cüzdan", and a
    // search that makes them find the right key first is not a search.
    $this->get('/az?q=cuzdan')
        ->assertSee('Qobu uzun cüzdan')
        ->assertDontSee('Aran ikiqat');
});

it('narrows rather than widens on a multi-word search', function () {
    $this->get('/en?q=qobu+longfold')
        ->assertSee('Qobu longfold')
        ->assertDontSee('Aran bifold');
});

it('says what was searched for, and how many matched', function () {
    $this->get('/en?q=buttero')
        ->assertSee(__('shop.collection.results_for', ['count' => 2, 'query' => 'buttero'], 'en'));
});

it('distinguishes an empty shop from a search that found nothing', function () {
    $this->get('/en?q=zzzznothing')
        ->assertSee(__('shop.collection.no_matches', [], 'en'))
        ->assertDontSee(__('shop.catalogue.empty', [], 'en'));
});

it('filters by category', function () {
    $this->get('/en?category=card')
        ->assertSee('Xazar sleeve')
        ->assertDontSee('Aran bifold');
});

it('filters by price band', function () {
    $this->get('/en?price=over_100')
        ->assertSee('Qobu longfold')
        ->assertDontSee('Xazar sleeve');

    $this->get('/en?price=under_50')
        ->assertSee('Xazar sleeve')
        ->assertDontSee('Aran bifold');
});

it('combines a search with a filter instead of letting one replace the other', function () {
    $this->get('/en?q=buttero&category=wallet')
        ->assertSee('Qobu longfold')
        ->assertDontSee('Xazar sleeve');

    $this->get('/en?q=cordovan&price=under_50')
        ->assertSee(__('shop.collection.no_matches', [], 'en'));
});

it('sorts by price in both directions', function () {
    $ascending = $this->get('/en?sort=price_asc')->getContent();
    $descending = $this->get('/en?sort=price_desc')->getContent();

    expect(strpos($ascending, 'Xazar sleeve'))->toBeLessThan(strpos($ascending, 'Qobu longfold'));
    expect(strpos($descending, 'Qobu longfold'))->toBeLessThan(strpos($descending, 'Xazar sleeve'));
});

it('ignores nonsense in the query string rather than emptying the grid', function () {
    // A hand-edited or stale URL should not produce a blank page with no
    // explanation of what happened.
    $this->get('/en?category=hovercraft&price=free&sort=sideways')
        ->assertSee('Aran bifold')
        ->assertSee('Xazar sleeve')
        ->assertSee('Qobu longfold');
});

it('keeps the search when a category tab is followed', function () {
    // The tab hrefs carry the rest of the state; losing the search on a tab
    // click is the classic way a filter bar becomes annoying.
    $this->get('/en?q=buttero')->assertSee('q=buttero&amp;category=wallet', false);
});

it('points the filter form at the grid, not the top of the page', function () {
    // A GET submit replaces the action URL's query but keeps its fragment.
    // Without #shop on the action, applying a sort or a filter landed back at
    // the very top of the page — the hero, if there is a photograph in it —
    // instead of back at the grid the control lives in.
    $this->get('/en')->assertSee(
        '<form method="GET" action="'.route('storefront.catalogue', absolute: false).'#shop"',
        escape: false
    );
});

it('does not carry an empty search through the filter form', function () {
    // The hidden field exists to preserve an active search across a filter
    // change. With no search running there is nothing to preserve, and
    // submitting it blank leaves "?q=" on every filtered URL.
    //
    // Asserted against the hidden input specifically: the header's own search
    // box is also name="q" and is legitimately empty here.
    $this->get('/en?category=wallet')
        ->assertDontSee('<input type="hidden" name="q"', escape: false);

    $this->get('/en?category=wallet&q=buttero')
        ->assertSee('<input type="hidden" name="q" value="buttero">', escape: false);
});
