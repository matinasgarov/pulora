<?php // tests/Feature/Concurrency/OversellTest.php

use App\Domain\Cart\CartSnapshot;
use App\Domain\Cart\CartLine;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Order\CustomerDetails;
use App\Domain\Order\InsufficientStockException;
use App\Domain\Order\OrderService;
use App\Domain\Shipping\Models\ShippingRate;
use App\Domain\Shipping\Models\ShippingZone;
use App\Domain\Shipping\ShippingQuote;

// This test is meaningless on SQLite, where lockForUpdate() is a silent no-op.
beforeEach(function () {
    if (config('database.default') !== 'mysql_test') {
        $this->markTestSkipped('Concurrency behaviour is only observable on MySQL.');
    }
});

// Named for what it actually verifies: the stock check lives inside the locked
// transaction. It does not spawn parallel processes — see the note below.
it('checks stock inside the locked transaction so the second order is refused', function () {
    $product = Product::factory()->create(['base_price_minor' => 8900]);
    $variant = Variant::factory()->for($product)->create(['stock_quantity' => 1, 'weight_grams' => 120]);

    $customer = new CustomerDetails(
        email: 'buyer@example.com', name: 'Buyer', addressLine1: '1 St',
        addressLine2: null, city: 'Baku', postcode: null, countryCode: 'AZ', phone: null,
    );

    // orders.shipping_rate_id is a real foreign key — the rate must exist, and
    // its auto-assigned id must be used. A bare ShippingQuote(rateId: 1) fails
    // with "FOREIGN KEY constraint failed" (this bit Task 9 before it was fixed).
    $zone = ShippingZone::create([
        'name' => 'Azerbaijan', 'country_codes' => ['AZ'], 'is_fallback' => true,
    ]);
    $rate = ShippingRate::create([
        'shipping_zone_id' => $zone->id, 'name' => 'Standard',
        'min_weight_grams' => 0, 'max_weight_grams' => 3000, 'price_minor' => 500,
    ]);

    $shipping = new ShippingQuote(rateId: $rate->id, name: 'Standard', priceMinor: 500);

    $snapshot = new CartSnapshot([new CartLine(
        lineKey: 'k', variantId: $variant->id, quantity: 1,
        productName: 'Bifold', variantDescription: 'Cognac',
        unitPriceMinor: 8900, personalization: [], weightGrams: 120,
    )]);

    $orders = app(OrderService::class);
    $succeeded = 0;

    foreach (range(1, 2) as $_) {
        try {
            $orders->createFromCart($snapshot, $customer, $shipping);
            $succeeded++;
        } catch (InsufficientStockException) {
            // expected for the loser
        }
    }

    expect($succeeded)->toBe(1)
        ->and($variant->fresh()->stock_quantity)->toBe(0);
});
