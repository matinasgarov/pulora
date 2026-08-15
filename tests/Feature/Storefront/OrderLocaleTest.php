<?php // tests/Feature/Storefront/OrderLocaleTest.php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Order\OrderStatus;
use App\Domain\Shipping\Models\ShippingRate;
use App\Domain\Shipping\Models\ShippingZone;
use App\Livewire\CheckoutForm;
use App\Mail\OrderConfirmation;
use App\Mail\ShipmentNotification;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

it('renders the lookup page inside the storefront layout', function () {
    $this->get('/orders/lookup')->assertSuccessful()->assertSee('Pulora');
});

it('finds an order and shows its snapshot lines', function () {
    $order = Order::factory()->create(['customer_email' => 'buyer@example.com']);
    OrderItem::factory()->for($order)->create(['product_name' => 'Bifold wallet']);

    $this->post('/orders/lookup', [
        'order_number' => $order->order_number,
        'email' => 'buyer@example.com',
    ])->assertSuccessful()->assertSee('Bifold wallet');
});

it('sends the shipment email in the language the customer ordered in', function () {
    Mail::fake();

    $order = Order::factory()->create([
        'status' => OrderStatus::InProduction,
        'paid_at' => now()->subDay(),
        'locale' => 'az',
    ]);
    OrderItem::factory()->for($order)->create();

    app(App\Domain\Order\OrderService::class)
        ->transition($order, OrderStatus::Shipped, trackingNumber: 'AZ123456789AZ');

    Mail::assertQueued(ShipmentNotification::class, function ($mail) {
        return $mail->order->locale === 'az';
    });
});

it('renders the shipment email body in Azerbaijani when the order was placed in az', function () {
    $order = Order::factory()->create(['locale' => 'az']);

    $mail = new ShipmentNotification($order->fresh());
    $mail->envelope();
    $rendered = $mail->render();

    expect($rendered)->toContain('göndərildi')
        ->and($rendered)->toContain(__('shop.mail.shipped_thanks', locale: 'az'));
});

it('renders the confirmation email body in Azerbaijani when the order was placed in az', function () {
    $order = Order::factory()->create(['locale' => 'az']);
    OrderItem::factory()->for($order)->create();

    $mail = new OrderConfirmation($order->fresh());
    $mail->envelope();
    $rendered = $mail->render();

    expect($rendered)->toContain(__('shop.mail.thank_you', ['name' => $order->customer_name], 'az'));
});

it('freezes order_items text in the locale the order was placed in, and records orders.locale', function () {
    $product = Product::factory()->create([
        'name' => ['en' => 'Bifold wallet', 'az' => 'İkiqat pulqabı'],
        'base_price_minor' => 8900,
        'is_active' => true,
    ]);
    $variant = Variant::factory()->for($product)->create([
        'stock_quantity' => 5, 'is_active' => true, 'weight_grams' => 120,
        'description' => ['en' => 'Cognac', 'az' => 'Konyak'],
    ]);

    $zone = ShippingZone::create(['name' => 'Azerbaijan', 'country_codes' => ['AZ'], 'is_fallback' => false]);
    $rate = ShippingRate::create([
        'shipping_zone_id' => $zone->id, 'name' => 'Standard',
        'min_weight_grams' => 0, 'max_weight_grams' => 3000, 'price_minor' => 500,
    ]);

    app(CartService::class)->add($variant->id, 1);

    app()->setLocale('az');

    livewire(CheckoutForm::class)
        ->set('country_code', 'AZ')
        ->set('shipping_rate_id', $rate->id)
        ->set('email', 'buyer@example.com')
        ->set('name', 'Buyer')
        ->set('address_line1', '1 Nizami St')
        ->set('city', 'Baku')
        ->call('submit');

    $order = Order::sole();
    $item = $order->items->sole();

    expect($order->locale)->toBe('az')
        ->and($item->product_name)->toBe('İkiqat pulqabı')
        ->and($item->variant_description)->toBe('Konyak');
});
