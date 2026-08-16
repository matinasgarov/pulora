<?php // tests/Feature/Storefront/HomepageTest.php

use App\Domain\Catalog\Models\PersonalizationOption;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Catalog\ProductTag;

it('renders the hero copy', function () {
    $this->get('/az')
        ->assertSee(__('shop.hero.line1'))
        ->assertSee(__('shop.hero.line2'))
        ->assertSee(__('shop.hero.line3'))
        ->assertSee(__('shop.hero.body'));
});

it('renders the collection title and the bespoke and atelier sections', function () {
    $this->get('/az')
        ->assertSee(__('shop.collection.title'))
        ->assertSee(__('shop.bespoke.heading'))
        ->assertSee(__('shop.atelier.quote'));
});

it('shows a real live count in the toolbar', function () {
    Product::factory()->count(2)->create(['is_active' => true]);

    $this->get('/az')->assertSee(__('shop.collection.count', ['count' => 2]));
});

it('renders the phase-2 tabs, filter button and sort as disabled controls', function () {
    $content = $this->get('/az')->getContent();

    expect(substr_count($content, 'disabled'))->toBeGreaterThanOrEqual(5); // 3 tabs + filter button + sort select
});

it('renders the bespoke starting price as a real AZN figure, not $220', function () {
    $response = $this->get('/az');

    $response->assertDontSee('$220');
    $response->assertSee(App\Domain\Money::format(25000));
});

it('shows a tag badge for a product carrying one', function () {
    Product::factory()->create(['is_active' => true, 'tag' => ProductTag::New->value]);

    $this->get('/az')->assertSee(ProductTag::New->label());
});

it('does not render a quick-add control for a product with more than one active variant', function () {
    $product = Product::factory()->create(['is_active' => true]);
    Variant::factory()->for($product)->count(2)->create(['is_active' => true]);

    $this->get('/az')->assertDontSee(__('shop.collection.quick_add'));
});

it('does not render a quick-add control for a product with a required personalization option', function () {
    $product = Product::factory()->create(['is_active' => true]);
    Variant::factory()->for($product)->create(['is_active' => true]);
    PersonalizationOption::create([
        'product_id' => $product->id,
        'type' => 'monogram',
        'label' => 'Monogram',
        'price_delta_minor' => 0,
        'max_characters' => 3,
        'allowed_pattern' => '/^[A-Z]+$/',
        'is_required' => true,
    ]);

    $this->get('/az')->assertDontSee(__('shop.collection.quick_add'));
});

it('renders a quick-add control for a product with exactly one active variant and no required personalization', function () {
    $product = Product::factory()->create(['is_active' => true]);
    Variant::factory()->for($product)->create(['is_active' => true]);

    $this->get('/az')->assertSee(__('shop.collection.quick_add'));
});
