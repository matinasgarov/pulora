<?php // tests/Feature/Order/MarkPaidTest.php

use App\Domain\Discount\Models\DiscountCode;
use App\Domain\Order\Models\Order;
use App\Domain\Order\MarkPaidOutcome;
use App\Domain\Order\OrderService;
use App\Domain\Order\OrderStatus;
use App\Mail\OrderConfirmation;
use App\Mail\PaymentAnomaly;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->orders = app(OrderService::class);
    $this->order = Order::create([
        'order_number' => 'LS-2026-PAID01', 'customer_email' => 'buyer@example.com',
        'customer_name' => 'Test Buyer', 'address_line1' => '1 Nizami St', 'city' => 'Baku',
        'country_code' => 'AZ', 'subtotal_minor' => 8900, 'shipping_minor' => 500,
        'total_minor' => 9400, 'currency' => 'AZN', 'reserved_until' => now()->addMinutes(30),
    ]);
});

it('transitions the order to paid and clears the reservation', function () {
    expect($this->orders->markPaid($this->order, 'REF-1', 9400, 'AZN'))->toBe(MarkPaidOutcome::Transitioned);

    $fresh = $this->order->fresh();
    expect($fresh->status)->toBe(OrderStatus::Paid)
        ->and($fresh->payment_reference)->toBe('REF-1')
        ->and($fresh->paid_at)->not->toBeNull()
        ->and($fresh->reserved_until)->toBeNull();
});

it('sends exactly one confirmation email', function () {
    $this->orders->markPaid($this->order, 'REF-1', 9400, 'AZN');

    Mail::assertQueuedCount(1);
    Mail::assertQueued(OrderConfirmation::class,
        fn ($m) => $m->hasTo('buyer@example.com'));
});

it('is idempotent across repeated callbacks', function () {
    expect($this->orders->markPaid($this->order, 'REF-1', 9400, 'AZN'))->toBe(MarkPaidOutcome::Transitioned);
    expect($this->orders->markPaid($this->order->fresh(), 'REF-1', 9400, 'AZN'))->toBe(MarkPaidOutcome::AlreadyPaid);
    expect($this->orders->markPaid($this->order->fresh(), 'REF-1', 9400, 'AZN'))->toBe(MarkPaidOutcome::AlreadyPaid);

    Mail::assertQueuedCount(1);
});

it('consumes the discount code exactly once', function () {
    $code = DiscountCode::create([
        'code' => 'LEATHER10', 'kind' => 'percent', 'value' => 10,
        'minimum_order_minor' => 0, 'usage_limit' => 5, 'times_used' => 0, 'is_active' => true,
    ]);
    $this->order->update(['discount_code_id' => $code->id]);

    $this->orders->markPaid($this->order->fresh(), 'REF-1', 9400, 'AZN');
    $this->orders->markPaid($this->order->fresh(), 'REF-1', 9400, 'AZN');

    expect($code->fresh()->times_used)->toBe(1);
});

it('does not resurrect a cancelled order', function () {
    $this->order->update(['status' => OrderStatus::Cancelled]);

    expect($this->orders->markPaid($this->order->fresh(), 'REF-1', 9400, 'AZN'))->toBe(MarkPaidOutcome::NotPayable);
    expect($this->order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('alerts the operator when a discount code was over-redeemed', function () {
    $code = DiscountCode::create([
        'code' => 'LEATHER10', 'kind' => 'percent', 'value' => 10,
        'minimum_order_minor' => 0, 'usage_limit' => 1, 'times_used' => 1, 'is_active' => true,
    ]);
    $this->order->update(['discount_code_id' => $code->id]);

    expect($this->orders->markPaid($this->order->fresh(), 'REF-1', 9400, 'AZN'))->toBe(MarkPaidOutcome::Transitioned);

    Mail::assertQueued(\App\Mail\PaymentAnomaly::class);
    expect($this->order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($code->fresh()->times_used)->toBe(1);
});

it('does not mark paid when the amount does not match the order total', function () {
    expect($this->orders->markPaid($this->order, 'REF-1', 100, 'AZN'))->toBe(MarkPaidOutcome::AmountMismatch);

    expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);
    Mail::assertNotQueued(OrderConfirmation::class);
});

it('does not mark paid when the currency does not match', function () {
    expect($this->orders->markPaid($this->order, 'REF-1', 9400, 'USD'))->toBe(MarkPaidOutcome::AmountMismatch);

    expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);
    Mail::assertNotQueued(OrderConfirmation::class);
});
