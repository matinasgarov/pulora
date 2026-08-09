<?php // tests/Feature/Order/ReleaseReservationsTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Order\OrderStatus;
use App\Jobs\ReleaseExpiredReservations;

function reservedOrder(Variant $variant, int $qty, $reservedUntil, OrderStatus $status = OrderStatus::PendingPayment): Order
{
    $order = Order::create([
        'order_number' => 'LS-' . fake()->unique()->bothify('####??'),
        'status' => $status,
        'customer_email' => 'buyer@example.com', 'customer_name' => 'Buyer',
        'address_line1' => '1 St', 'city' => 'Baku', 'country_code' => 'AZ',
        'subtotal_minor' => 8900, 'shipping_minor' => 500, 'total_minor' => 9400,
        'reserved_until' => $reservedUntil,
    ]);

    OrderItem::create([
        'order_id' => $order->id, 'variant_id' => $variant->id,
        'product_name' => 'Bifold', 'variant_description' => 'Cognac', 'sku' => $variant->sku,
        'unit_price_minor' => 8900, 'quantity' => $qty, 'line_total_minor' => 8900 * $qty,
        'weight_grams' => 120,
    ]);

    return $order;
}

beforeEach(function () {
    $this->variant = Variant::factory()->for(Product::factory())->create(['stock_quantity' => 3]);
});

it('returns stock and cancels an expired unpaid order', function () {
    $order = reservedOrder($this->variant, 2, now()->subMinute());

    (new ReleaseExpiredReservations)->handle();

    expect($this->variant->fresh()->stock_quantity)->toBe(5)
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()->reserved_until)->toBeNull();
});

it('leaves a reservation that has not expired alone', function () {
    $order = reservedOrder($this->variant, 2, now()->addMinutes(10));

    (new ReleaseExpiredReservations)->handle();

    expect($this->variant->fresh()->stock_quantity)->toBe(3)
        ->and($order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

it('never touches a paid order', function () {
    $order = reservedOrder($this->variant, 2, now()->subHour(), OrderStatus::Paid);

    (new ReleaseExpiredReservations)->handle();

    expect($this->variant->fresh()->stock_quantity)->toBe(3)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('does not double-release when run twice', function () {
    reservedOrder($this->variant, 2, now()->subMinute());

    (new ReleaseExpiredReservations)->handle();
    (new ReleaseExpiredReservations)->handle();

    expect($this->variant->fresh()->stock_quantity)->toBe(5);
});
