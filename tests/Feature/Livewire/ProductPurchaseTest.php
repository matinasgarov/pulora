<?php // tests/Feature/Livewire/ProductPurchaseTest.php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\Models\PersonalizationOption;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Livewire\ProductPurchase;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->product = Product::factory()->create(['base_price_minor' => 8900, 'is_active' => true]);
    $this->variant = Variant::factory()->for($this->product)->create([
        'stock_quantity' => 5,
        'price_minor_override' => null,
        'is_active' => true,
    ]);
});

it('starts on the first available variant', function () {
    livewire(ProductPurchase::class, ['product' => $this->product])
        ->assertSet('variantId', $this->variant->id);
});

it('shows the effective price', function () {
    livewire(ProductPurchase::class, ['product' => $this->product])
        ->assertSee(App\Domain\Money::format(8900));
});

it('prefers the variant price override', function () {
    $this->variant->update(['price_minor_override' => 9500]);

    livewire(ProductPurchase::class, ['product' => $this->product->fresh()])
        ->assertSee(App\Domain\Money::format(9500));
});

it('adds the variant to the cart', function () {
    livewire(ProductPurchase::class, ['product' => $this->product])
        ->call('add');

    expect(app(CartService::class)->snapshot()->lines)->toHaveCount(1);
});

it('announces the change so the header can update', function () {
    livewire(ProductPurchase::class, ['product' => $this->product])
        ->call('add')
        ->assertDispatched('cart-updated');
});

it('adds the personalization delta to the displayed price', function () {
    PersonalizationOption::create([
        'product_id' => $this->product->id,
        'type' => 'monogram',
        'label' => 'Monogram',
        'price_delta_minor' => 500,
        'max_characters' => 3,
        'allowed_pattern' => '/^[A-Z]+$/',
        'is_required' => false,
    ]);

    livewire(ProductPurchase::class, ['product' => $this->product->fresh()])
        ->set('personalization.monogram', 'MA')
        ->assertSee(App\Domain\Money::format(9400));
});

it('surfaces a personalization rule violation instead of adding the line', function () {
    PersonalizationOption::create([
        'product_id' => $this->product->id,
        'type' => 'monogram',
        'label' => 'Monogram',
        'price_delta_minor' => 0,
        'max_characters' => 3,
        'allowed_pattern' => '/^[A-Z]+$/',
        'is_required' => false,
    ]);

    livewire(ProductPurchase::class, ['product' => $this->product->fresh()])
        ->set('personalization.monogram', 'lowercase!')
        ->call('add')
        ->assertHasErrors('personalization.monogram');

    expect(app(CartService::class)->snapshot()->lines)->toBeEmpty();
});

it('refuses to add a variant with no remaining capacity', function () {
    $this->variant->update(['stock_quantity' => 0]);

    livewire(ProductPurchase::class, ['product' => $this->product->fresh()])
        ->call('add');

    expect(app(CartService::class)->snapshot()->lines)->toBeEmpty();
});

it('renders a colour swatch for a variant whose description is a known leather colour', function () {
    $this->variant->update(['description' => 'Cognac']);

    livewire(ProductPurchase::class, ['product' => $this->product->fresh()])
        ->assertSee('#a3612f', false);
});

it('falls back to a labelled text button for a variant colour it does not recognise', function () {
    $this->variant->update(['description' => 'Shell cordovan']);

    livewire(ProductPurchase::class, ['product' => $this->product->fresh()])
        ->assertDontSee('background-color', false)
        ->assertSee('Shell cordovan');
});

it('selecting a swatch selects that variant', function () {
    $second = Variant::factory()->for($this->product)->create([
        'description' => 'Black', 'is_active' => true, 'stock_quantity' => 5,
    ]);

    livewire(ProductPurchase::class, ['product' => $this->product->fresh()])
        ->call('$set', 'variantId', $second->id)
        ->assertSet('variantId', $second->id);
});
