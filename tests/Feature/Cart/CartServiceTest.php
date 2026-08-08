<?php // tests/Feature/Cart/CartServiceTest.php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\Models\PersonalizationOption;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;

beforeEach(function () {
    $this->product = Product::factory()->create(['base_price_minor' => 8900, 'name' => 'Bifold']);
    $this->variant = Variant::factory()->for($this->product)->create([
        'stock_quantity' => 5, 'weight_grams' => 120, 'description' => 'Cognac',
    ]);
    $this->cart = app(CartService::class);
});

it('prices a line from the database, not from the caller', function () {
    $this->cart->add($this->variant->id, 2);

    $snapshot = $this->cart->snapshot();

    expect($snapshot->lines)->toHaveCount(1)
        ->and($snapshot->lines[0]->unitPriceMinor)->toBe(8900)
        ->and($snapshot->lines[0]->lineTotalMinor())->toBe(17800)
        ->and($snapshot->subtotalMinor())->toBe(17800)
        ->and($snapshot->totalWeightGrams())->toBe(240);
});

it('reflects a price change made after the item was added', function () {
    $this->cart->add($this->variant->id, 1);
    $this->product->update(['base_price_minor' => 9900]);

    expect($this->cart->snapshot()->subtotalMinor())->toBe(9900);
});

it('adds the personalization delta to the unit price', function () {
    PersonalizationOption::create([
        'product_id' => $this->product->id, 'type' => 'monogram', 'label' => 'Monogram',
        'price_delta_minor' => 1000, 'max_characters' => 3,
        'allowed_pattern' => '/^[A-Z]+$/', 'is_required' => false,
    ]);

    $this->cart->add($this->variant->id, 1, ['monogram' => 'ma']);
    $snapshot = $this->cart->snapshot();

    expect($snapshot->lines[0]->unitPriceMinor)->toBe(9900)
        ->and($snapshot->lines[0]->personalization)->toBe(['monogram' => 'MA']);
});

it('keeps differently personalized lines separate', function () {
    PersonalizationOption::create([
        'product_id' => $this->product->id, 'type' => 'monogram', 'label' => 'Monogram',
        'price_delta_minor' => 1000, 'max_characters' => 3,
        'allowed_pattern' => '/^[A-Z]+$/', 'is_required' => false,
    ]);

    $this->cart->add($this->variant->id, 1, ['monogram' => 'AA']);
    $this->cart->add($this->variant->id, 1, ['monogram' => 'BB']);

    expect($this->cart->snapshot()->lines)->toHaveCount(2);
});

it('merges quantity for identical lines', function () {
    $this->cart->add($this->variant->id, 1);
    $this->cart->add($this->variant->id, 2);

    $snapshot = $this->cart->snapshot();
    expect($snapshot->lines)->toHaveCount(1)
        ->and($snapshot->lines[0]->quantity)->toBe(3);
});

it('drops lines whose variant became inactive', function () {
    $this->cart->add($this->variant->id, 1);
    $this->variant->update(['is_active' => false]);

    expect($this->cart->snapshot()->isEmpty())->toBeTrue();
});
