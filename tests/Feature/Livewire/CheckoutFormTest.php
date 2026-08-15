<?php // tests/Feature/Livewire/CheckoutFormTest.php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Models\ShippingRate;
use App\Domain\Shipping\Models\ShippingZone;
use App\Livewire\CheckoutForm;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $product = Product::factory()->create(['base_price_minor' => 8900, 'is_active' => true]);
    $this->variant = Variant::factory()->for($product)->create([
        'stock_quantity' => 5, 'is_active' => true, 'weight_grams' => 120,
    ]);

    // is_fallback => false: ShippingCalculator::zoneFor() treats a
    // fallback zone as a catch-all for every unmatched country (see
    // ShippingZoneResource and ShippingCalculatorTest), so a fallback zone
    // here would also "serve" ZZ and the no-shipping test below could never
    // observe an empty quote list. Matching on country_codes alone is what
    // the "offers shipping options" test needs anyway.
    $zone = ShippingZone::create([
        'name' => 'Azerbaijan', 'country_codes' => ['AZ'], 'is_fallback' => false,
    ]);
    $this->rate = ShippingRate::create([
        'shipping_zone_id' => $zone->id, 'name' => 'Standard',
        'min_weight_grams' => 0, 'max_weight_grams' => 3000, 'price_minor' => 500,
    ]);

    app(CartService::class)->add($this->variant->id, 1);
});

it('offers shipping options once a country is chosen', function () {
    livewire(CheckoutForm::class)
        ->set('country_code', 'AZ')
        ->assertSee('Standard');
});

it('reports honestly when nothing ships to that country', function () {
    livewire(CheckoutForm::class)
        ->set('country_code', 'ZZ')
        ->assertSee(__('shop.checkout.no_shipping'));
});

it('places an order through the shared PlaceOrder path', function () {
    livewire(CheckoutForm::class)
        ->set('country_code', 'AZ')
        ->set('shipping_rate_id', $this->rate->id)
        ->set('email', 'buyer@example.com')
        ->set('name', 'Buyer')
        ->set('address_line1', '1 Nizami St')
        ->set('city', 'Baku')
        ->call('submit');

    expect(Order::count())->toBe(1);
});

it('records the locale the customer bought in', function () {
    app()->setLocale('az');

    livewire(CheckoutForm::class)
        ->set('country_code', 'AZ')
        ->set('shipping_rate_id', $this->rate->id)
        ->set('email', 'buyer@example.com')
        ->set('name', 'Buyer')
        ->set('address_line1', '1 Nizami St')
        ->set('city', 'Baku')
        ->call('submit');

    expect(Order::sole()->locale)->toBe('az');
});

it('refuses to submit without an email', function () {
    livewire(CheckoutForm::class)
        ->set('country_code', 'AZ')
        ->set('shipping_rate_id', $this->rate->id)
        ->set('name', 'Buyer')
        ->set('address_line1', '1 Nizami St')
        ->set('city', 'Baku')
        ->call('submit')
        ->assertHasErrors('email');

    expect(Order::count())->toBe(0);
});
