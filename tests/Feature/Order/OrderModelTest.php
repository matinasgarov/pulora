<?php // tests/Feature/Order/OrderModelTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Order\OrderStatus;

it('casts status to the enum', function () {
    $order = Order::create(orderAttributes());

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

it('keeps item snapshots after the source product changes', function () {
    $product = Product::factory()->create([
        'name' => 'Bifold',
        'base_price_minor' => 8900,
    ]);
    $variant = Variant::factory()->for($product)->create([
        'sku' => 'WAL-1',
        'description' => 'Cognac',
    ]);

    $order = Order::create(orderAttributes());

    OrderItem::create([
        'order_id' => $order->id,
        'variant_id' => $variant->id,
        'product_name' => $product->name,
        'variant_description' => $variant->description,
        'sku' => $variant->sku,
        'unit_price_minor' => $variant->effectivePriceMinor(),
        'quantity' => 1,
        'line_total_minor' => $variant->effectivePriceMinor(),
        'personalization' => ['monogram' => 'MA'],
        'weight_grams' => 120,
    ]);

    // The catalogue moves on: renamed, repriced, re-coloured.
    $product->update(['name' => 'Renamed', 'base_price_minor' => 20000]);
    $variant->update(['description' => 'Black', 'sku' => 'WAL-999']);

    // The order still shows what the customer actually bought and paid.
    $item = $order->items()->first();

    expect($item->product_name)->toBe('Bifold')
        ->and($item->variant_description)->toBe('Cognac')
        ->and($item->sku)->toBe('WAL-1')
        ->and($item->unit_price_minor)->toBe(8900)
        ->and($item->personalization)->toBe(['monogram' => 'MA']);
});

it('keeps order items after the variant is deleted', function () {
    $product = Product::factory()->create(['name' => 'Bifold']);
    $variant = Variant::factory()->for($product)->create(['sku' => 'WAL-1']);
    $order = Order::create(orderAttributes());

    OrderItem::create([
        'order_id' => $order->id,
        'variant_id' => $variant->id,
        'product_name' => 'Bifold', 'variant_description' => 'Cognac', 'sku' => 'WAL-1',
        'unit_price_minor' => 8900, 'quantity' => 1, 'line_total_minor' => 8900,
        'weight_grams' => 120,
    ]);

    $variant->delete();

    $item = $order->items()->first();

    expect($item)->not->toBeNull()
        ->and($item->variant_id)->toBeNull()
        ->and($item->product_name)->toBe('Bifold')
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
