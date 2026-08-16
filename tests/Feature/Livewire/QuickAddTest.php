<?php // tests/Feature/Livewire/QuickAddTest.php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Livewire\QuickAdd;

use function Pest\Livewire\livewire;

it('adds the single active variant to the cart', function () {
    $product = Product::factory()->create();
    $variant = Variant::factory()->for($product)->create(['is_active' => true, 'stock_quantity' => 5]);

    livewire(QuickAdd::class, ['product' => $product])->call('add');

    $lines = app(CartService::class)->snapshot()->lines;
    expect($lines)->toHaveCount(1);
    expect($lines[0]->variantId)->toBe($variant->id);
});

it('announces the change so the header badge can update', function () {
    $product = Product::factory()->create();
    Variant::factory()->for($product)->create(['is_active' => true]);

    livewire(QuickAdd::class, ['product' => $product])
        ->call('add')
        ->assertDispatched('cart-updated');
});
