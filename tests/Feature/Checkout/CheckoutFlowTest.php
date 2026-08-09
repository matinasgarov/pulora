<?php // tests/Feature/Checkout/CheckoutFlowTest.php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Order\Models\Order;
use App\Domain\Order\OrderStatus;
use App\Domain\Shipping\Models\ShippingRate;
use App\Domain\Shipping\Models\ShippingZone;

beforeEach(function () {
    $this->product = Product::factory()->create(['base_price_minor' => 8900]);
    $this->variant = Variant::factory()->for($this->product)->create([
        'stock_quantity' => 5, 'weight_grams' => 120,
    ]);

    $zone = ShippingZone::create(['name' => 'Azerbaijan', 'country_codes' => ['AZ'], 'is_fallback' => true]);
    $this->rate = ShippingRate::create([
        'shipping_zone_id' => $zone->id, 'name' => 'Standard',
        'min_weight_grams' => 0, 'max_weight_grams' => 2000, 'price_minor' => 500,
    ]);
});

function checkoutPayload(array $overrides = []): array
{
    return array_merge([
        'email' => 'buyer@example.com',
        'name' => 'Test Buyer',
        'address_line1' => '1 Nizami St',
        'city' => 'Baku',
        'country_code' => 'AZ',
        'postcode' => 'AZ1000',
    ], $overrides);
}

it('creates a pending order and redirects to the gateway', function () {
    app(CartService::class)->add($this->variant->id, 1);

    $response = $this->post('/checkout', checkoutPayload(['shipping_rate_id' => $this->rate->id]));

    $order = Order::first();
    expect($order->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->total_minor)->toBe(9400);

    $response->assertRedirect(route('payment.mock.form', ['reference' => 'MOCK-' . $order->order_number]));
});

it('ignores a total supplied by the browser', function () {
    app(CartService::class)->add($this->variant->id, 1);

    $this->post('/checkout', checkoutPayload([
        'shipping_rate_id' => $this->rate->id,
        'total_minor' => 1,          // attacker-supplied
        'subtotal_minor' => 1,
    ]));

    expect(Order::first()->total_minor)->toBe(9400);
});

it('rejects a shipping rate that does not serve the destination', function () {
    $other = ShippingZone::create(['name' => 'Nowhere', 'country_codes' => ['XX'], 'is_fallback' => false]);
    $badRate = ShippingRate::create([
        'shipping_zone_id' => $other->id, 'name' => 'Free',
        'min_weight_grams' => 0, 'max_weight_grams' => 2000, 'price_minor' => 0,
    ]);
    app(CartService::class)->add($this->variant->id, 1);

    $this->post('/checkout', checkoutPayload(['shipping_rate_id' => $badRate->id]))
        ->assertSessionHasErrors('shipping_rate_id');

    expect(Order::count())->toBe(0);
});

it('refuses checkout when stock ran out, before any redirect', function () {
    app(CartService::class)->add($this->variant->id, 5);
    $this->variant->update(['stock_quantity' => 1]);

    $this->post('/checkout', checkoutPayload(['shipping_rate_id' => $this->rate->id]))
        ->assertSessionHasErrors('cart');

    expect(Order::count())->toBe(0)
        ->and($this->variant->fresh()->stock_quantity)->toBe(1);
});

it('rejects an empty cart', function () {
    $this->post('/checkout', checkoutPayload(['shipping_rate_id' => $this->rate->id]))
        ->assertSessionHasErrors('cart');
});

it('requires a valid email and address', function () {
    app(CartService::class)->add($this->variant->id, 1);

    $this->post('/checkout', ['email' => 'not-an-email', 'shipping_rate_id' => $this->rate->id])
        ->assertSessionHasErrors(['email', 'name', 'address_line1', 'city', 'country_code']);
});

it('preserves the cart after redirecting to the gateway, before payment completes', function () {
    app(CartService::class)->add($this->variant->id, 1);

    $this->post('/checkout', checkoutPayload(['shipping_rate_id' => $this->rate->id]))
        ->assertRedirect();

    // Spec §5: an abandoned payment leaves the cart preserved. It is only cleared
    // once the confirmation page finds the order actually Paid.
    expect(app(CartService::class)->snapshot()->isEmpty())->toBeFalse();
});

it('clears the cart on the confirmation page once the order is paid', function () {
    app(CartService::class)->add($this->variant->id, 1);

    $this->post('/checkout', checkoutPayload(['shipping_rate_id' => $this->rate->id]));

    $order = Order::first();
    $order->update(['status' => OrderStatus::Paid]);

    $this->get('/checkout/confirmation')->assertOk();

    expect(app(CartService::class)->snapshot()->isEmpty())->toBeTrue();
});

it('does not clear the cart on the confirmation page while the order is still pending', function () {
    app(CartService::class)->add($this->variant->id, 1);

    $this->post('/checkout', checkoutPayload(['shipping_rate_id' => $this->rate->id]));

    $this->get('/checkout/confirmation')->assertOk();

    expect(app(CartService::class)->snapshot()->isEmpty())->toBeFalse();
});

it('keeps the order and the cart when the gateway is unreachable', function () {
    app(CartService::class)->add($this->variant->id, 1);

    $this->mock(\App\Domain\Payment\PaymentGateway::class, function ($mock) {
        $mock->shouldReceive('createPayment')
            ->andThrow(new RuntimeException('gateway timeout'));
    });

    $this->post('/checkout', checkoutPayload(['shipping_rate_id' => $this->rate->id]))
        ->assertSessionHasErrors('payment');

    // The order survives, unpaid and unreferenced, for the expiry job to reclaim.
    $order = Order::first();
    expect($order)->not->toBeNull()
        ->and($order->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->payment_reference)->toBeNull();

    // The customer's basket is untouched, so retrying costs them nothing.
    expect(app(CartService::class)->snapshot()->isEmpty())->toBeFalse();
});
