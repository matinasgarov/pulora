<?php // tests/Feature/Admin/WorkshopTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Order\OrderStatus;
use App\Filament\Pages\Workshop;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Mail::fake();
    $this->actingAs(User::factory()->create(['is_operator' => true]));

    $variant = Variant::factory()->for(Product::factory())->create();

    $this->makeOrder = function (OrderStatus $status, array $extra = []) use ($variant) {
        $order = Order::factory()->create(array_merge([
            'status' => $status,
            'paid_at' => now()->subDay(),
        ], $extra));

        OrderItem::factory()->for($order)->create([
            'variant_id' => $variant->id,
            'personalization' => ['monogram' => 'MA'],
        ]);

        return $order;
    };
});

/** Reads a column off the mounted page as a plain array of ids. */
function columnIds(Testable $page, string $property): array
{
    return collect($page->get($property))->pluck('id')->all();
}

it('puts a paid order in the to-make column', function () {
    $order = ($this->makeOrder)(OrderStatus::Paid);

    $page = livewire(Workshop::class)->assertSee($order->order_number);

    expect(columnIds($page, 'toMake'))->toBe([$order->id])
        ->and(columnIds($page, 'inProduction'))->toBe([]);
});

it('puts an in-production order in the making column', function () {
    $order = ($this->makeOrder)(OrderStatus::InProduction);

    $page = livewire(Workshop::class);

    expect(columnIds($page, 'inProduction'))->toBe([$order->id])
        ->and(columnIds($page, 'toMake'))->toBe([]);
});

it('puts an order marked made in the ready-to-post column', function () {
    $order = ($this->makeOrder)(OrderStatus::InProduction, ['ready_at' => now()]);

    $page = livewire(Workshop::class);

    expect(columnIds($page, 'readyToPost'))->toBe([$order->id])
        ->and(columnIds($page, 'inProduction'))->toBe([]);
});

it('does not show orders that are not yet paid', function () {
    $order = ($this->makeOrder)(OrderStatus::PendingPayment, ['paid_at' => null]);

    livewire(Workshop::class)->assertDontSee($order->order_number);
});

it('does not show shipped orders', function () {
    $order = ($this->makeOrder)(OrderStatus::Shipped);

    livewire(Workshop::class)->assertDontSee($order->order_number);
});

it('shows the monogram on the card', function () {
    ($this->makeOrder)(OrderStatus::Paid);

    livewire(Workshop::class)->assertSee('MA');
});

it('counts days waiting from paid_at, not created_at', function () {
    $order = ($this->makeOrder)(OrderStatus::Paid, ['paid_at' => now()->subDays(5)]);
    $order->update(['created_at' => now()->subDays(30)]);

    livewire(Workshop::class)->assertSee('5 days');
});

it('moves an order across when the card action is used', function () {
    $order = ($this->makeOrder)(OrderStatus::Paid);

    livewire(Workshop::class)
        ->callAction('start_production', arguments: ['order' => $order->id]);

    expect($order->fresh()->status)->toBe(OrderStatus::InProduction);
});

it('marks an order ready from the card action', function () {
    $order = ($this->makeOrder)(OrderStatus::InProduction);

    livewire(Workshop::class)
        ->callAction('mark_ready', arguments: ['order' => $order->id]);

    expect($order->fresh()->ready_at)->not->toBeNull();
});

it('ships an order from the card action with a tracking number', function () {
    $order = ($this->makeOrder)(OrderStatus::InProduction, ['ready_at' => now()]);

    livewire(Workshop::class)
        ->callAction('ship', arguments: ['order' => $order->id], data: ['tracking_number' => 'AZ123456789AZ']);

    expect($order->fresh())
        ->status->toBe(OrderStatus::Shipped)
        ->tracking_number->toBe('AZ123456789AZ');
});

it('shows the number strip', function () {
    ($this->makeOrder)(OrderStatus::PendingPayment, ['paid_at' => null]);
    ($this->makeOrder)(OrderStatus::Paid, ['paid_at' => now()->subDays(9)]);

    livewire(Workshop::class)
        ->assertSet('awaitingPayment', 1)
        ->assertSet('overdue', 1);
});

it('excludes cancelled and refunded orders from this month\'s revenue', function () {
    ($this->makeOrder)(OrderStatus::Paid, ['paid_at' => now(), 'total_minor' => 5000]);
    ($this->makeOrder)(OrderStatus::Refunded, ['paid_at' => now(), 'total_minor' => 9900]);
    ($this->makeOrder)(OrderStatus::Cancelled, ['paid_at' => now(), 'total_minor' => 9900]);

    livewire(Workshop::class)->assertSet('revenueThisMonthMinor', 5000);
});
