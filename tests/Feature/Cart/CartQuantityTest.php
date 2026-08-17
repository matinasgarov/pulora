<?php // tests/Feature/Cart/CartQuantityTest.php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use App\Domain\Catalog\Models\Variant;
use App\Livewire\CartPage;
use Livewire\Livewire;

function bagWithOneLine(int $quantity = 1): array
{
    $product = Product::factory()->create(['is_active' => true, 'name' => ['en' => 'Aran bifold', 'az' => 'Aran ikiqat']]);
    $variant = Variant::factory()->for($product)->create(['is_active' => true, 'stock_quantity' => 10]);

    $cart = app(CartService::class);
    $cart->add($variant->id, $quantity);

    return [$cart, $product, $cart->snapshot()->lines[0]->lineKey];
}

it('steps a line up and down', function () {
    [$cart, , $lineKey] = bagWithOneLine(2);

    $cart->setQuantity($lineKey, 5);
    expect($cart->snapshot()->lines[0]->quantity)->toBe(5);

    $cart->setQuantity($lineKey, 1);
    expect($cart->snapshot()->lines[0]->quantity)->toBe(1);
});

it('removes the line rather than storing a quantity of zero', function () {
    // A stepper that can reach zero is the same gesture as removing, and a
    // line of nothing is not something snapshot() should have to reason about.
    [$cart, , $lineKey] = bagWithOneLine();

    $cart->setQuantity($lineKey, 0);

    expect($cart->snapshot()->lines)->toBe([]);
});

it('ignores a quantity change for a line that is no longer in the bag', function () {
    [$cart] = bagWithOneLine();

    $cart->setQuantity('a-key-that-was-retired', 4);

    expect($cart->snapshot()->lines)->toHaveCount(1);
});

it('steps from the stored quantity, not from what the page last rendered', function () {
    // Two quick clicks must not both act on the same stale number and lose one
    // of the steps, so the component re-reads the cart rather than trusting a
    // value passed up from the browser.
    [$cart, , $lineKey] = bagWithOneLine(1);

    Livewire::test(CartPage::class)
        ->call('step', $lineKey, 1)
        ->call('step', $lineKey, 1);

    expect($cart->snapshot()->lines[0]->quantity)->toBe(3);
});

it('removes a line when it is stepped below one', function () {
    [$cart, , $lineKey] = bagWithOneLine(1);

    Livewire::test(CartPage::class)->call('step', $lineKey, -1);

    expect($cart->snapshot()->lines)->toBe([]);
});

it('re-renders after an action without a locale in the URL defaults', function () {
    // A Livewire action posts to /livewire/update, which never passes through
    // the storefront's locale-prefixed routes, so the {locale} default the
    // checkout link needs is not set for it. Clearing the defaults here
    // reproduces that; before this was moved from mount() to boot(), pressing
    // any button on the bag threw UrlGenerationException.
    [, , $lineKey] = bagWithOneLine(2);

    $component = Livewire::test(CartPage::class);

    // Cleared *after* the initial render, because that is the shape of the
    // real thing: the first request set the default via middleware, and the
    // update request that follows has none of it.
    //
    // Reaching for the internals because URL::defaults() merges — passing an
    // empty array is a no-op, so the obvious way to write this quietly tests
    // nothing at all.
    $generator = app('url');
    (new ReflectionMethod($generator, 'routeUrl'))->invoke($generator)->defaultParameters = [];

    $component->call('step', $lineKey, 1)
        ->assertSee(route('storefront.checkout', ['locale' => app()->getLocale()], absolute: false), escape: false);
});

it('carries the product photograph onto the cart line', function () {
    $product = Product::factory()->create(['is_active' => true]);
    $variant = Variant::factory()->for($product)->create(['is_active' => true]);
    ProductImage::create(['product_id' => $product->id, 'path' => 'shot-b.jpg', 'alt_text' => 'Second', 'sort_order' => 1]);
    ProductImage::create(['product_id' => $product->id, 'path' => 'shot-a.jpg', 'alt_text' => 'First', 'sort_order' => 0]);

    app(CartService::class)->add($variant->id, 1);

    // The first photograph in sort order — the same one the grid tile shows,
    // so an item looks the same in the bag as it did in the shop.
    expect(app(CartService::class)->snapshot()->lines[0]->imagePath)->toBe('shot-a.jpg');
});

it('leaves the image null for a product with no photography yet', function () {
    $product = Product::factory()->create(['is_active' => true]);
    $variant = Variant::factory()->for($product)->create(['is_active' => true]);

    app(CartService::class)->add($variant->id, 1);

    expect(app(CartService::class)->snapshot()->lines[0]->imagePath)->toBeNull();
});
