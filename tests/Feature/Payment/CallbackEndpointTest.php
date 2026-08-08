<?php // tests/Feature/Payment/CallbackEndpointTest.php

use App\Domain\Order\Models\Order;
use App\Domain\Order\OrderStatus;
use App\Mail\OrderConfirmation;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->order = Order::create([
        'order_number' => 'LS-2026-CB01', 'customer_email' => 'buyer@example.com',
        'customer_name' => 'Test Buyer', 'address_line1' => '1 Nizami St', 'city' => 'Baku',
        'country_code' => 'AZ', 'subtotal_minor' => 8900, 'shipping_minor' => 500,
        'total_minor' => 9400, 'payment_reference' => 'MOCK-LS-2026-CB01',
        'reserved_until' => now()->addMinutes(30),
    ]);
});

function signedCallback(string $reference, string $status = 'paid'): array
{
    return [
        'reference' => $reference,
        'status' => $status,
        'signature' => hash_hmac('sha256', "{$reference}|{$status}", config('services.payment.mock_secret')),
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

    Mail::assertSentCount(1);
    expect(\App\Domain\Payment\Models\PaymentLog::where('direction', 'callback')->count())->toBe(3);
});

it('leaves the order untouched on a forged signature', function () {
    $this->post('/payment/callback', [
        'reference' => 'MOCK-LS-2026-CB01', 'status' => 'paid', 'signature' => 'forged',
    ])->assertStatus(400);

    expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);

    // The operator IS alerted here (see Step 4); the customer is not.
    Mail::assertNotSent(OrderConfirmation::class);
});

it('ignores a callback for an unknown reference', function () {
    $this->post('/payment/callback', signedCallback('MOCK-DOES-NOT-EXIST'))->assertStatus(404);

    Mail::assertNotSent(OrderConfirmation::class);
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

    Mail::assertSent(\App\Mail\PaymentAnomaly::class,
        fn ($m) => $m->hasTo('owner@example.com'));
});
