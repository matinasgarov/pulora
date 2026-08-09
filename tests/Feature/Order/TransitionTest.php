<?php // tests/Feature/Order/TransitionTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Order\IllegalTransitionException;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderEvent;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Order\OrderService;
use App\Domain\Order\OrderStatus;
use App\Domain\Payment\PaymentGateway;
use App\Domain\Payment\Models\PaymentLog;
use App\Domain\Payment\RefundResult;
use App\Mail\ShipmentNotification;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->orders = app(OrderService::class);

    $this->product = Product::factory()->create();
    $this->variant = Variant::factory()->for($this->product)->create(['stock_quantity' => 7]);
    $this->operator = App\Models\User::factory()->create();

    $this->order = Order::factory()->create([
        'status' => OrderStatus::Paid,
        'paid_at' => now()->subDay(),
    ]);

    OrderItem::factory()->for($this->order)->create([
        'variant_id' => $this->variant->id,
        'quantity' => 2,
    ]);
});

it('moves a paid order into production', function () {
    $this->orders->transition($this->order, OrderStatus::InProduction);

    expect($this->order->fresh()->status)->toBe(OrderStatus::InProduction);
});

it('records an order event with the from and to statuses and the acting user', function () {
    $this->orders->transition($this->order, OrderStatus::InProduction, 'Cutting today', userId: $this->operator->id);

    $event = OrderEvent::where('order_id', $this->order->id)->sole();

    expect($event->from_status)->toBe('paid')
        ->and($event->to_status)->toBe('in_production')
        ->and($event->note)->toBe('Cutting today')
        ->and($event->user_id)->toBe($this->operator->id);
});

it('refuses to record an event for a user that does not exist', function () {
    expect(fn () => App\Domain\Order\Models\OrderEvent::create([
        'order_id' => $this->order->id,
        'from_status' => 'paid',
        'to_status' => 'in_production',
        'user_id' => 999999,
        'created_at' => now(),
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('rejects an illegal transition without writing the row', function () {
    expect(fn () => $this->orders->transition($this->order, OrderStatus::Delivered))
        ->toThrow(IllegalTransitionException::class);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(OrderEvent::count())->toBe(0);
});

it('refuses to set an order to paid', function () {
    $order = Order::factory()->create(['status' => OrderStatus::InProduction]);

    expect(fn () => $this->orders->transition($order, OrderStatus::Paid))
        ->toThrow(IllegalTransitionException::class);
});

it('refuses to set an order back to pending payment', function () {
    expect(fn () => $this->orders->transition($this->order, OrderStatus::PendingPayment))
        ->toThrow(IllegalTransitionException::class);
});

it('restores capacity when an order is cancelled', function () {
    $this->orders->transition($this->order, OrderStatus::Cancelled, 'Customer changed their mind');

    expect($this->variant->fresh()->stock_quantity)->toBe(9);
});

it('does not restore capacity on a refund by default', function () {
    $this->orders->transition($this->order, OrderStatus::Refunded);

    expect($this->variant->fresh()->stock_quantity)->toBe(7);
});

it('restores capacity on a refund when asked to', function () {
    $this->orders->transition($this->order, OrderStatus::Refunded, restoreCapacity: true);

    expect($this->variant->fresh()->stock_quantity)->toBe(9);
});

it('records a payment log when refunding', function () {
    $this->orders->transition($this->order, OrderStatus::Refunded);

    // MockGateway::refund() writes its own 'request' row, so filter by direction
    // rather than expecting a single log for this order.
    $log = PaymentLog::where('order_id', $this->order->id)->where('direction', 'refund')->sole();

    expect($log->payload['succeeded'])->toBeTrue()
        ->and($log->payload['amount_minor'])->toBe($this->order->total_minor);
});

it('leaves the order unchanged when the gateway refuses the refund', function () {
    $this->mock(PaymentGateway::class, function ($mock) {
        $mock->shouldReceive('refund')->once()
            ->andReturn(new RefundResult(succeeded: false, reference: 'DECLINED-1'));
    });

    expect(fn () => app(OrderService::class)->transition($this->order, OrderStatus::Refunded))
        ->toThrow(RuntimeException::class);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(OrderEvent::count())->toBe(0);
});

it('records the tracking number and shipped time and emails the customer', function () {
    $this->orders->transition($this->order, OrderStatus::InProduction);
    $this->orders->transition($this->order->fresh(), OrderStatus::Shipped, trackingNumber: 'AZ123456789AZ');

    $fresh = $this->order->fresh();

    expect($fresh->status)->toBe(OrderStatus::Shipped)
        ->and($fresh->tracking_number)->toBe('AZ123456789AZ')
        ->and($fresh->shipped_at)->not->toBeNull();

    Mail::assertQueued(ShipmentNotification::class,
        fn ($m) => $m->hasTo($this->order->customer_email));
});

it('refuses to ship without a tracking number', function () {
    $this->orders->transition($this->order, OrderStatus::InProduction);

    expect(fn () => $this->orders->transition($this->order->fresh(), OrderStatus::Shipped))
        ->toThrow(InvalidArgumentException::class);

    expect($this->order->fresh()->status)->toBe(OrderStatus::InProduction);
});

it('marks an order ready without changing its status', function () {
    $this->orders->transition($this->order, OrderStatus::InProduction);
    $this->orders->markReady($this->order->fresh());

    $fresh = $this->order->fresh();

    expect($fresh->ready_at)->not->toBeNull()
        ->and($fresh->status)->toBe(OrderStatus::InProduction);
});

it('does not restore capacity twice if cancel is somehow called again', function () {
    $this->orders->transition($this->order, OrderStatus::Cancelled);

    expect(fn () => $this->orders->transition($this->order->fresh(), OrderStatus::Cancelled))
        ->toThrow(IllegalTransitionException::class);

    expect($this->variant->fresh()->stock_quantity)->toBe(9);
});
