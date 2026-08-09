<?php // tests/Feature/Order/CreateOrderTest.php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Discount\DiscountResult;
use App\Domain\Discount\Models\DiscountCode;
use App\Domain\Order\CustomerDetails;
use App\Domain\Order\InsufficientStockException;
use App\Domain\Order\OrderService;
use App\Domain\Order\OrderStatus;
use App\Domain\Shipping\Models\ShippingRate;
use App\Domain\Shipping\Models\ShippingZone;
use App\Domain\Shipping\ShippingQuote;

beforeEach(function () {
    $this->product = Product::factory()->create(['base_price_minor' => 8900, 'name' => 'Bifold']);
    $this->variant = Variant::factory()->for($this->product)->create([
        'stock_quantity' => 5, 'weight_grams' => 120, 'sku' => 'WAL-1', 'description' => 'Cognac',
    ]);
    $this->cart = app(CartService::class);
    $this->orders = app(OrderService::class);
    $this->customer = new CustomerDetails(
        email: 'buyer@example.com', name: 'Test Buyer',
        addressLine1: '1 Nizami St', addressLine2: null, city: 'Baku',
        postcode: 'AZ1000', countryCode: 'AZ', phone: null,
    );
    $zone = ShippingZone::create([
        'name' => 'Azerbaijan',
        'country_codes' => ['AZ'],
        'is_fallback' => true,
    ]);

    $rate = ShippingRate::create([
        'shipping_zone_id' => $zone->id,
        'name' => 'Standard',
        'min_weight_grams' => 0,
        'max_weight_grams' => 3000,
        'price_minor' => 500,
    ]);

    $this->shipping = new ShippingQuote(
        rateId: $rate->id,
        name: 'Standard',
        priceMinor: 500,
    );
});

it('creates a pending order with correct totals', function () {
    $this->cart->add($this->variant->id, 2);

    $order = $this->orders->createFromCart($this->cart->snapshot(), $this->customer, $this->shipping);

    expect($order->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->subtotal_minor)->toBe(17800)
        ->and($order->shipping_minor)->toBe(500)
        ->and($order->discount_minor)->toBe(0)
        ->and($order->total_minor)->toBe(18300)
        ->and($order->currency)->toBe('AZN')
        ->and($order->source)->toBe('web')
        ->and($order->total_weight_grams)->toBe(240)
        ->and($order->order_number)->toStartWith('LS-')
        ->and($order->shipping_rate_id)->toBe($this->shipping->rateId);
});

it('subtracts the discount from the total but not from shipping', function () {
    $this->cart->add($this->variant->id, 1);
    $code = DiscountCode::create([
        'code' => 'LEATHER10',
        'kind' => 'percent',
        'value' => 10,
        'minimum_order_minor' => 0,
        'usage_limit' => null,
        'times_used' => 0,
        'is_active' => true,
    ]);
    $discount = new DiscountResult(codeId: $code->id, code: 'LEATHER10', amountMinor: 890);

    $order = $this->orders->createFromCart($this->cart->snapshot(), $this->customer, $this->shipping, $discount);

    expect($order->discount_minor)->toBe(890)
        ->and($order->total_minor)->toBe(8900 - 890 + 500)
        ->and($order->discount_code_id)->toBe($code->id);
});

it('snapshots item details rather than referencing live data', function () {
    $this->cart->add($this->variant->id, 1);
    $order = $this->orders->createFromCart($this->cart->snapshot(), $this->customer, $this->shipping);

    $this->product->update(['name' => 'Renamed', 'base_price_minor' => 20000]);

    $item = $order->items()->first();
    expect($item->product_name)->toBe('Bifold')
        ->and($item->unit_price_minor)->toBe(8900)
        ->and($item->sku)->toBe('WAL-1');
});

it('reserves stock immediately and sets an expiry', function () {
    $this->cart->add($this->variant->id, 2);
    $order = $this->orders->createFromCart($this->cart->snapshot(), $this->customer, $this->shipping);

    expect($this->variant->fresh()->stock_quantity)->toBe(3)
        ->and($order->reserved_until->isFuture())->toBeTrue();
});

it('refuses to create an order that exceeds available stock', function () {
    $this->cart->add($this->variant->id, 99);

    $this->orders->createFromCart($this->cart->snapshot(), $this->customer, $this->shipping);
})->throws(InsufficientStockException::class);

it('leaves stock untouched when creation fails', function () {
    $this->cart->add($this->variant->id, 99);

    try {
        $this->orders->createFromCart($this->cart->snapshot(), $this->customer, $this->shipping);
    } catch (InsufficientStockException) {
        // expected
    }

    expect($this->variant->fresh()->stock_quantity)->toBe(5)
        ->and(\App\Domain\Order\Models\Order::count())->toBe(0);
});

it('generates unique order numbers', function () {
    $numbers = collect(range(1, 5))->map(function () {
        $this->cart->clear();
        $this->cart->add($this->variant->id, 1);
        return $this->orders->createFromCart($this->cart->snapshot(), $this->customer, $this->shipping)->order_number;
    });

    expect($numbers->unique())->toHaveCount(5);
});
