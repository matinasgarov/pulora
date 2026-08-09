<?php // tests/Feature/Order/OrderLookupTest.php

use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;

beforeEach(function () {
    $this->order = Order::create([
        'order_number' => 'LS-2026-LOOKUP1', 'customer_email' => 'Buyer@Example.com',
        'customer_name' => 'Test Buyer', 'address_line1' => '1 Nizami St', 'city' => 'Baku',
        'country_code' => 'AZ', 'subtotal_minor' => 8900, 'shipping_minor' => 500,
        'total_minor' => 9400, 'currency' => 'AZN',
    ]);

    OrderItem::create([
        'order_id' => $this->order->id, 'product_name' => 'Bifold Wallet',
        'variant_description' => 'Cognac', 'sku' => 'BF-COG',
        'unit_price_minor' => 8900, 'quantity' => 1, 'line_total_minor' => 8900,
        'weight_grams' => 120,
    ]);
});

it('shows the order for a correct email and order number pair', function () {
    $response = $this->post('/orders/lookup', [
        'email' => 'buyer@example.com',
        'order_number' => 'ls-2026-lookup1',
    ]);

    $response->assertOk();
    $response->assertSee('LS-2026-LOOKUP1');
    $response->assertSee('Bifold Wallet');
});

it('shows a not-found message for a wrong email with a right order number, without leaking order data', function () {
    $response = $this->post('/orders/lookup', [
        'email' => 'someone-else@example.com',
        'order_number' => 'LS-2026-LOOKUP1',
    ]);

    $response->assertOk();
    $response->assertDontSee('Bifold Wallet');
    $response->assertDontSee('9400');
});

it('returns an identical response for a wrong email and for an unknown order number', function () {
    $wrongEmail = $this->post('/orders/lookup', [
        'email' => 'someone-else@example.com',
        'order_number' => 'LS-2026-LOOKUP1',
    ]);

    $unknownOrder = $this->post('/orders/lookup', [
        'email' => 'buyer@example.com',
        'order_number' => 'LS-2026-DOESNOTEXIST',
    ]);

    expect($wrongEmail->status())->toBe($unknownOrder->status());
    expect($wrongEmail->getContent())->toBe($unknownOrder->getContent());
});

it('shows the lookup form', function () {
    $this->get('/orders/lookup')->assertOk();
});
