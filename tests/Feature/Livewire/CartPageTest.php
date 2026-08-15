<?php // tests/Feature/Livewire/CartPageTest.php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Livewire\CartCount;
use App\Livewire\CartPage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->product = Product::factory()->create(['base_price_minor' => 8900, 'is_active' => true]);
    $this->variant = Variant::factory()->for($this->product)->create([
        'stock_quantity' => 5, 'is_active' => true, 'weight_grams' => 120,
    ]);
});

it('shows an empty state when nothing has been added', function () {
    livewire(CartPage::class)->assertSee(__('shop.cart.empty'));
});

it('lists a line that was added', function () {
    app(CartService::class)->add($this->variant->id, 1);

    livewire(CartPage::class)->assertSee($this->product->name);
});

it('shows the subtotal', function () {
    app(CartService::class)->add($this->variant->id, 2);

    livewire(CartPage::class)->assertSee(App\Domain\Money::format(17800));
});

it('removes a line', function () {
    app(CartService::class)->add($this->variant->id, 1);
    $lineKey = app(CartService::class)->snapshot()->lines[0]->lineKey;

    livewire(CartPage::class)->call('remove', $lineKey);

    expect(app(CartService::class)->snapshot()->lines)->toBeEmpty();
});

it('tells the customer when a line disappeared because the product was retired', function () {
    app(CartService::class)->add($this->variant->id, 1);

    // The operator deactivates it while the cart is still open.
    $this->product->update(['is_active' => false]);

    livewire(CartPage::class)->assertSee(__('shop.cart.line_removed'));
});

it('counts the lines in the header', function () {
    app(CartService::class)->add($this->variant->id, 3);

    livewire(CartCount::class)->assertSee('3');
});

it('refreshes the header count when a product page adds something', function () {
    livewire(CartCount::class)
        ->assertSee('0')
        ->call('$refresh');

    app(CartService::class)->add($this->variant->id, 1);

    livewire(CartCount::class)->assertSee('1');
});
