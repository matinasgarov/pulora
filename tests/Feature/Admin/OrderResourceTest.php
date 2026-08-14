<?php // tests/Feature/Admin/OrderResourceTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderEvent;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Order\OrderService;
use App\Domain\Order\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Mail::fake();
    $this->user = User::factory()->create(['is_operator' => true]);
    $this->actingAs($this->user);

    $this->product = Product::factory()->create();
    $this->variant = Variant::factory()->for($this->product)->create(['stock_quantity' => 5]);

    $this->order = Order::factory()->create([
        'status' => OrderStatus::Paid,
        'paid_at' => now()->subDays(2),
    ]);

    OrderItem::factory()->for($this->order)->create([
        'variant_id' => $this->variant->id,
        'quantity' => 1,
        'personalization' => ['monogram' => 'MA'],
    ]);
});

it('lists orders', function () {
    livewire(ListOrders::class)->assertCanSeeTableRecords([$this->order]);
});

it('has no create action', function () {
    expect(OrderResource::canCreate())->toBeFalse();
});

it('shows the snapshot line items and the personalization', function () {
    livewire(ViewOrder::class, ['record' => $this->order->getKey()])
        ->assertSee('MA')
        ->assertSee($this->order->order_number);
});

// An operator reading "MA, yes" cannot tell the monogram from the gift wrap and
// cuts the wrong thing, so each value must stay attached to its own label.
it('labels every key of a multi-key personalization', function () {
    $order = Order::factory()->create([
        'status' => OrderStatus::Paid,
        'paid_at' => now()->subDays(2),
    ]);

    OrderItem::factory()->for($order)->create([
        'variant_id' => $this->variant->id,
        'quantity' => 1,
        'personalization' => ['monogram' => 'MA', 'gift_wrap' => 'yes'],
    ]);

    livewire(ViewOrder::class, ['record' => $order->getKey()])
        ->assertSee('Monogram: MA')
        ->assertSee('Gift Wrap: yes');
});

it('shows the snapshot name even after the product is renamed', function () {
    $this->product->update(['name' => 'Completely different name']);

    livewire(ViewOrder::class, ['record' => $this->order->getKey()])
        ->assertSee('Bifold wallet')
        ->assertDontSee('Completely different name');
});

it('moves an order into production through the action', function () {
    livewire(ViewOrder::class, ['record' => $this->order->getKey()])
        ->callAction('start_production');

    expect($this->order->fresh()->status)->toBe(OrderStatus::InProduction);
});

it('records the acting user on the event', function () {
    livewire(ViewOrder::class, ['record' => $this->order->getKey()])
        ->callAction('start_production');

    expect(OrderEvent::sole()->user_id)->toBe($this->user->id);
});

it('requires a tracking number to ship', function () {
    $this->order->update(['status' => OrderStatus::InProduction]);

    livewire(ViewOrder::class, ['record' => $this->order->getKey()])
        ->callAction('ship', data: ['tracking_number' => ''])
        ->assertHasActionErrors(['tracking_number']);

    expect($this->order->fresh()->status)->toBe(OrderStatus::InProduction);
});

it('ships with a tracking number', function () {
    $this->order->update(['status' => OrderStatus::InProduction]);

    livewire(ViewOrder::class, ['record' => $this->order->getKey()])
        ->callAction('ship', data: ['tracking_number' => 'AZ123456789AZ']);

    expect($this->order->fresh()->tracking_number)->toBe('AZ123456789AZ');
});

it('restores capacity when cancelled from the panel', function () {
    livewire(ViewOrder::class, ['record' => $this->order->getKey()])
        ->callAction('cancel', data: ['note' => 'Customer changed their mind']);

    expect($this->variant->fresh()->stock_quantity)->toBe(6);
});

it('hides the production action on an order that is already shipped', function () {
    $this->order->update(['status' => OrderStatus::Shipped]);

    livewire(ViewOrder::class, ['record' => $this->order->getKey()])
        ->assertActionHidden('start_production');
});

it('shows the event history', function () {
    app(OrderService::class)
        ->transition($this->order, OrderStatus::InProduction, 'Cutting today', $this->user->id);

    livewire(ViewOrder::class, ['record' => $this->order->fresh()->getKey()])
        ->assertSee('Cutting today');
});
