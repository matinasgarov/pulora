<?php // tests/Feature/Order/OrderModelTest.php

use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Order\OrderStatus;

it('casts status to the enum', function () {
    $order = Order::create(orderAttributes());

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

it('keeps item snapshots after the source product changes', function () {
    $order = Order::create(orderAttributes());
    OrderItem::create([
        'order_id' => $order->id, 'variant_id' => null,
        'product_name' => 'Bifold', 'variant_description' => 'Cognac', 'sku' => 'WAL-1',
        'unit_price_minor' => 8900, 'quantity' => 1, 'line_total_minor' => 8900,
        'personalization' => ['monogram' => 'MA'], 'weight_grams' => 120,
    ]);

    $item = $order->items()->first();

    expect($item->product_name)->toBe('Bifold')
        ->and($item->personalization)->toBe(['monogram' => 'MA'])
        ->and($item->unit_price_minor)->toBe(8900);
});

function orderAttributes(array $overrides = []): array
{
    return array_merge([
        'order_number' => 'LS-2026-0001',
        'customer_email' => 'buyer@example.com',
        'customer_name' => 'Test Buyer',
        'address_line1' => '1 Nizami St',
        'city' => 'Baku',
        'country_code' => 'AZ',
        'subtotal_minor' => 8900,
        'shipping_minor' => 500,
        'discount_minor' => 0,
        'total_minor' => 9400,
    ], $overrides);
}
