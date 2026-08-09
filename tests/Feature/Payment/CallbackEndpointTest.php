<?php // tests/Feature/Payment/CallbackEndpointTest.php

use App\Domain\Order\Models\Order;
use App\Domain\Order\OrderStatus;
use App\Mail\OrderConfirmation;
use App\Mail\PaymentAnomaly;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->order = Order::create([
        'order_number' => 'LS-2026-CB01', 'customer_email' => 'buyer@example.com',
        'customer_name' => 'Test Buyer', 'address_line1' => '1 Nizami St', 'city' => 'Baku',
        'country_code' => 'AZ', 'subtotal_minor' => 8900, 'shipping_minor' => 500,
        'total_minor' => 9400, 'currency' => 'AZN', 'payment_reference' => 'MOCK-LS-2026-CB01',
        'reserved_until' => now()->addMinutes(30),
    ]);
});

function signedCallback(string $reference, string $status = 'paid', int $amountMinor = 9400, string $currency = 'AZN'): array
{
    return [
        'reference' => $reference,
        'status' => $status,
        'amount_minor' => $amountMinor,
        'currency' => $currency,
        'signature' => hash_hmac('sha256', "{$reference}|{$status}|{$amountMinor}|{$currency}", config('services.payment.mock_secret')),
    ];
}

it('marks the order paid on a valid callback', function () {
    $this->post('/payment/callback', signedCallback('MOCK-LS-2026-CB01'))->assertOk();

    expect($this->order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('accepts the callback without a CSRF token', function () {
    $this->post('/payment/callback', signedCallback('MOCK-LS-2026-CB01'))
        ->assertOk();
});

it('processes a duplicate callback exactly once', function () {
    $payload = signedCallback('MOCK-LS-2026-CB01');

    $this->post('/payment/callback', $payload)->assertOk();
    $this->post('/payment/callback', $payload)->assertOk();
    $this->post('/payment/callback', $payload)->assertOk();

    Mail::assertQueuedCount(1);
    expect(\App\Domain\Payment\Models\PaymentLog::where('direction', 'callback')->count())->toBe(3);
});

it('leaves the order untouched on a forged signature', function () {
    $this->post('/payment/callback', [
        'reference' => 'MOCK-LS-2026-CB01', 'status' => 'paid', 'signature' => 'forged',
    ])->assertStatus(400);

    expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);

    // The operator IS alerted here (see Step 4); the customer is not.
    Mail::assertNotQueued(OrderConfirmation::class);
});

it('ignores a callback for an unknown reference', function () {
    $this->post('/payment/callback', signedCallback('MOCK-DOES-NOT-EXIST'))->assertStatus(404);

    Mail::assertNotQueued(OrderConfirmation::class);
});

it('does not mark paid when the gateway reports failure', function () {
    $this->post('/payment/callback', signedCallback('MOCK-LS-2026-CB01', 'failed'))->assertOk();

    expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

it('emails the operator when a callback signature is forged', function () {
    config(['shop.operator_email' => 'owner@example.com']);

    $this->post('/payment/callback', [
        'reference' => 'MOCK-LS-2026-CB01', 'status' => 'paid', 'signature' => 'forged',
    ])->assertStatus(400);

    Mail::assertQueued(\App\Mail\PaymentAnomaly::class,
        fn ($m) => $m->hasTo('owner@example.com'));
});

it('returns 200 for a late payment on an order that was already cancelled, leaves it cancelled, and alerts the operator without confirming the customer', function () {
    config(['shop.operator_email' => 'owner@example.com']);
    $this->order->update(['status' => OrderStatus::Cancelled, 'reserved_until' => null]);

    $this->post('/payment/callback', signedCallback('MOCK-LS-2026-CB01'))->assertOk();

    expect($this->order->fresh()->status)->toBe(OrderStatus::Cancelled);
    Mail::assertNotQueued(OrderConfirmation::class);
    Mail::assertQueued(PaymentAnomaly::class,
        fn ($m) => $m->hasTo('owner@example.com') && str_contains($m->reason, 'refund required'));
});

it('does not mark paid when the callback amount is short of the order total', function () {
    config(['shop.operator_email' => 'owner@example.com']);

    $this->post('/payment/callback', signedCallback('MOCK-LS-2026-CB01', 'paid', 100))->assertOk();

    expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);
    Mail::assertNotQueued(OrderConfirmation::class);
    Mail::assertQueued(PaymentAnomaly::class,
        fn ($m) => $m->hasTo('owner@example.com'));
});
