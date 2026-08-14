<?php // tests/Concurrency/attempt-order.php

/*
 * A standalone worker for ConcurrentOversellTest.
 *
 * This is deliberately NOT an artisan command: nothing in the shipped
 * application should be able to place an order from the console, so the
 * concurrency harness stays inside tests/ where it cannot reach production.
 *
 * Boots the framework, waits for a start time shared with its siblings, then
 * attempts to buy one unit of a variant. Prints exactly one token on stdout:
 *
 *   OK        - the order was created
 *   REFUSED   - InsufficientStockException, i.e. this worker lost the race
 *   ERROR ... - anything else, which the parent test treats as a failure
 *
 * Usage: php attempt-order.php <variantId> <rateId> <startAtUnixMicrotime>
 */

use App\Domain\Cart\CartLine;
use App\Domain\Cart\CartSnapshot;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Order\CustomerDetails;
use App\Domain\Order\InsufficientStockException;
use App\Domain\Order\OrderService;
use App\Domain\Shipping\ShippingQuote;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $variantId, $rateId, $startAt] = $argv;

try {
    $variant = Variant::with('product')->findOrFail($variantId);

    $cart = new CartSnapshot([new CartLine(
        lineKey: 'race',
        variantId: $variant->id,
        quantity: 1,
        productName: $variant->product->name,
        variantDescription: (string) $variant->description,
        unitPriceMinor: $variant->price_minor_override ?? $variant->product->base_price_minor,
        personalization: [],
        weightGrams: $variant->weight_grams,
    )]);

    $customer = new CustomerDetails(
        email: 'racer@example.com', name: 'Racer', addressLine1: '1 St',
        addressLine2: null, city: 'Baku', postcode: null, countryCode: 'AZ', phone: null,
    );

    $shipping = new ShippingQuote(rateId: (int) $rateId, name: 'Standard', priceMinor: 500);

    // Everything above is setup and must not count as part of the race. Spin
    // (rather than sleep) to the shared start time so every worker enters the
    // transaction within the same instant and genuinely contends for the row
    // lock — a stagger of even a few milliseconds would let each transaction
    // finish before the next began, which is the sequential case we already
    // cover in OversellTest.
    $startAt = (float) $startAt;

    while (microtime(true) < $startAt) {
        // Busy-wait: usleep() would overshoot by more than the window we want.
    }

    app(OrderService::class)->createFromCart($cart, $customer, $shipping);

    echo "OK\n";
} catch (InsufficientStockException) {
    echo "REFUSED\n";
} catch (Throwable $e) {
    echo 'ERROR '.$e::class.': '.$e->getMessage()."\n";
}
