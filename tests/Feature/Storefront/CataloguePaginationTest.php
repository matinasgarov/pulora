<?php // tests/Feature/Storefront/CataloguePaginationTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\ProductCategory;

beforeEach(function () {
    // 14 — enough for a second page without filling it, so the last page's
    // partial row is exercised too.
    Product::factory()->count(14)->create([
        'is_active' => true,
        'category' => ProductCategory::Wallet->value,
    ]);
});

it('shows twelve products on a page — three rows of the four-across grid', function () {
    $products = $this->get('/en')->viewData('products');

    expect($products->count())->toBe(12)
        ->and($products->perPage())->toBe(12)
        ->and($products->lastPage())->toBe(2);
});

it('puts the remainder on a second page', function () {
    $first = Product::orderBy('id')->first();
    $last = Product::orderBy('id')->get()->last();

    $this->get('/en')->assertSee($first->name)->assertDontSee($last->name);
    $this->get('/en?page=2')->assertSee($last->name)->assertDontSee($first->name);
});

it('counts every match, not just the ones on this page', function () {
    // The toolbar count answers "how many are there", so paging must not make
    // it read 12.
    $this->get('/en')->assertSee(__('shop.collection.count', ['count' => 14], 'en'));
});

it('carries the filter state and the grid anchor through a page link', function () {
    $this->get('/en?category=wallet&sort=price_desc')
        ->assertSee('category=wallet', false)
        ->assertSee('sort=price_desc', false)
        ->assertSee('page=2', false)
        ->assertSee('#shop', false);
});

it('paginates the matches rather than the whole catalogue', function () {
    // Filtering after paging would show a near-empty first page whenever a
    // filter excluded most of the leading products.
    Product::factory()->count(3)->create([
        'is_active' => true,
        'category' => ProductCategory::Card->value,
        'name' => ['en' => 'Findable card piece', 'az' => 'Findable card piece'],
    ]);

    $this->get('/en?category=card')
        ->assertSee('Findable card piece')
        ->assertSee(__('shop.collection.count', ['count' => 3], 'en'));
});

it('renders no pagination when everything fits on one page', function () {
    Product::query()->delete();
    Product::factory()->count(5)->create(['is_active' => true]);

    $this->get('/en')->assertDontSee(__('shop.collection.pagination.label', [], 'en'));
});

it('sends an out-of-range page back to the last real one', function () {
    // Otherwise the grid renders empty with no pager to escape by, while the
    // toolbar still reports the full count — a dead end from a stale link.
    $this->get('/en?page=99')
        ->assertRedirect()
        ->assertRedirectContains('page=2');
});

it('keeps the filter state when redirecting an out-of-range page', function () {
    $this->get('/en?category=wallet&page=99')->assertRedirectContains('category=wallet');
});
