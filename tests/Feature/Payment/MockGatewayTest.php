<?php // tests/Feature/Payment/MockGatewayTest.php

use App\Domain\Order\Models\Order;
use App\Domain\Payment\Models\PaymentLog;
use App\Domain\Payment\PaymentGateway;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->gateway = app(PaymentGateway::class);
    $this->order = Order::create([
        'order_number' => 'LS-2026-TEST01', 'customer_email' => 'buyer@example.com',
        'customer_name' => 'Test Buyer', 'address_line1' => '1 Nizami St', 'city' => 'Baku',
        'country_code' => 'AZ', 'subtotal_minor' => 8900, 'shipping_minor' => 500,
        'total_minor' => 9400, 'currency' => 'AZN', 'payment_reference' => 'MOCK-LS-2026-TEST01',
    ]);
});

it('resolves the mock gateway in the test environment', function () {
    expect($this->gateway)->toBeInstanceOf(\App\Domain\Payment\MockGateway::class);
});

it('returns a redirect carrying a reference derived from the order', function () {
    $redirect = $this->gateway->createPayment($this->order);

    expect($redirect->reference)->toBe('MOCK-LS-2026-TEST01')
        ->and($redirect->url)->toContain('MOCK-LS-2026-TEST01');
});

it('logs the payment request', function () {
    $this->gateway->createPayment($this->order);

    $log = PaymentLog::where('order_id', $this->order->id)->where('direction', 'request')->first();
    expect($log)->not->toBeNull()
        ->and($log->gateway)->toBe('mock');
});

it('accepts a correctly signed callback', function () {
    $result = $this->gateway->verifyCallback(Request::create('/callback', 'POST', [
        'reference' => 'MOCK-LS-2026-TEST01',
        'status' => 'paid',
        'signature' => hash_hmac('sha256', 'MOCK-LS-2026-TEST01|paid|9400|AZN', 'test-secret'),
    ]));

    expect($result->isValid)->toBeTrue()
        ->and($result->isPaid)->toBeTrue()
        ->and($result->reference)->toBe('MOCK-LS-2026-TEST01')
        ->and($result->amountMinor)->toBe(9400)
        ->and($result->currency)->toBe('AZN');
});

it('accepts a callback with an explicit amount and currency', function () {
    $result = $this->gateway->verifyCallback(Request::create('/callback', 'POST', [
        'reference' => 'MOCK-LS-2026-TEST01',
        'status' => 'paid',
        'amount_minor' => 100,
        'currency' => 'USD',
        'signature' => hash_hmac('sha256', 'MOCK-LS-2026-TEST01|paid|100|USD', 'test-secret'),
    ]));

    expect($result->isValid)->toBeTrue()
        ->and($result->amountMinor)->toBe(100)
        ->and($result->currency)->toBe('USD');
});

it('rejects a callback with a bad signature', function () {
    $result = $this->gateway->verifyCallback(Request::create('/callback', 'POST', [
        'reference' => 'MOCK-LS-2026-TEST01',
        'status' => 'paid',
        'signature' => 'forged',
    ]));

    expect($result->isValid)->toBeFalse()
        ->and($result->isPaid)->toBeFalse();
});

it('logs callbacks including invalid ones', function () {
    $this->gateway->verifyCallback(Request::create('/callback', 'POST', [
        'reference' => 'MOCK-LS-2026-TEST01', 'status' => 'paid', 'signature' => 'forged',
    ]));

    expect(PaymentLog::where('direction', 'callback')->count())->toBe(1);
});
