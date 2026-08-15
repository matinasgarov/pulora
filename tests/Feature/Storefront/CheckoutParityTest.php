<?php // tests/Feature/Storefront/CheckoutParityTest.php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\Models\PersonalizationOption;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Models\ShippingRate;
use App\Domain\Shipping\Models\ShippingZone;
use App\Http\Requests\CheckoutRequest;
use App\Livewire\CheckoutForm;

use function Pest\Livewire\livewire;

/**
 * Two entry points, one implementation — including the validation surface.
 *
 * POST /checkout and the Livewire form share PlaceOrder, but the Livewire form
 * originally restated the rules by hand and drifted: it was missing the length
 * caps on phone, postcode and address_line2. Those are string(255)/string(32)
 * columns on a connection running in strict mode, so an over-long value reached
 * Order::create() and died as an uncaught QueryException instead of a field
 * error — and only in production, since SQLite does not enforce column widths.
 */
it('validates exactly what the POST route validates', function () {
    $component = livewire(CheckoutForm::class)->call('submit');

    // Every rule the form request enforces on a required field must also be
    // enforced here; a rule present in one and absent in the other is the drift
    // this test exists to catch.
    foreach (array_keys((new CheckoutRequest)->rules()) as $field) {
        expect(property_exists(CheckoutForm::class, $field))
            ->toBeTrue("CheckoutForm has no `{$field}` property, so it cannot validate the rule the POST route applies to it.");
    }

    // country_code is not asserted here: it defaults to 'AZ', so `required`
    // is already satisfied on an untouched form.
    $component->assertHasErrors(['email', 'name', 'address_line1', 'city']);
});

it('rejects an over-long optional field instead of letting it reach the database', function () {
    livewire(CheckoutForm::class)
        ->set('email', 'buyer@example.com')
        ->set('name', 'Buyer')
        ->set('address_line1', '1 Nizami St')
        ->set('address_line2', str_repeat('x', 300))
        ->set('city', 'Baku')
        ->set('postcode', str_repeat('9', 50))
        ->set('phone', str_repeat('5', 50))
        ->set('country_code', 'AZ')
        ->set('shipping_rate_id', 1)
        ->call('submit')
        ->assertHasErrors(['address_line2', 'postcode', 'phone']);
});

/**
 * price_delta_minor is applied by CartService::snapshot() when it builds the
 * line, and mirrored read-only by the product page for display. If either the
 * cart or the order ever added it again, every personalised order would
 * overcharge — silently, and only for customers who personalised.
 */
it('charges a personalization delta exactly once, end to end', function () {
    $product = Product::factory()->create(['is_active' => true, 'base_price_minor' => 8900]);
    $variant = Variant::factory()->for($product)->create([
        'is_active' => true, 'stock_quantity' => 5, 'weight_grams' => 120,
        'price_minor_override' => null,
    ]);
    PersonalizationOption::create([
        'product_id' => $product->id, 'type' => 'monogram', 'label' => 'Monogram',
        'max_characters' => 3, 'allowed_pattern' => '/^[A-Z]+$/',
        'is_required' => false, 'price_delta_minor' => 500,
    ]);

    $zone = ShippingZone::create(['name' => 'AZ', 'country_codes' => ['AZ'], 'is_fallback' => false]);
    $rate = ShippingRate::create([
        'shipping_zone_id' => $zone->id, 'name' => 'Standard',
        'min_weight_grams' => 0, 'max_weight_grams' => 3000, 'price_minor' => 500,
    ]);

    app(CartService::class)->add($variant->id, 1, ['monogram' => 'MA']);

    // 8900 base + 500 delta. Not 9400 + 500 again.
    expect(app(CartService::class)->snapshot()->subtotalMinor())->toBe(9400);

    livewire(CheckoutForm::class)
        ->set('country_code', 'AZ')
        ->set('shipping_rate_id', $rate->id)
        ->set('email', 'buyer@example.com')
        ->set('name', 'Buyer')
        ->set('address_line1', '1 Nizami St')
        ->set('city', 'Baku')
        ->call('submit');

    $order = Order::sole();

    expect($order->items->sole()->unit_price_minor)->toBe(9400)
        ->and($order->subtotal_minor)->toBe(9400)
        ->and($order->total_minor)->toBe(9900); // + 500 shipping
});
