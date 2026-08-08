<?php // app/Domain/Order/OrderService.php

namespace App\Domain\Order;

use App\Domain\Cart\CartLine;
use App\Domain\Cart\CartSnapshot;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Discount\DiscountResult;
use App\Domain\Discount\DiscountService;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Shipping\ShippingQuote;
use App\Mail\OrderConfirmation;
use App\Mail\PaymentAnomaly;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OrderService
{
    public const RESERVATION_MINUTES = 30;

    public function __construct(private DiscountService $discounts) {}

    public function markPaid(
        Order $order,
        string $paymentReference,
        int $paidAmountMinor,
        string $paidCurrency,
    ): MarkPaidOutcome {
        $overRedeemedCode = false;

        $outcome = DB::transaction(function () use ($order, $paymentReference, $paidAmountMinor, $paidCurrency, &$overRedeemedCode) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $locked) {
                return MarkPaidOutcome::NotPayable;
            }

            if ($locked->status === OrderStatus::Paid) {
                return MarkPaidOutcome::AlreadyPaid;
            }

            if ($locked->status !== OrderStatus::PendingPayment) {
                return MarkPaidOutcome::NotPayable;
            }

            if ($paidAmountMinor !== $locked->total_minor || $paidCurrency !== $locked->currency) {
                return MarkPaidOutcome::AmountMismatch;
            }

            $locked->update([
                'status' => OrderStatus::Paid,
                'payment_reference' => $paymentReference,
                'paid_at' => now(),
                'reserved_until' => null,
            ]);

            if ($locked->discount_code_id) {
                // consume() is conditional: false means the code was already at its
                // limit when a concurrent checkout beat us to the last use. Payment
                // has already been taken, so the order stands and the operator is
                // told — the customer is never punished for our race.
                $overRedeemedCode = ! $this->discounts->consume($locked->discount_code_id);
            }

            return MarkPaidOutcome::Transitioned;
        });

        if ($outcome === MarkPaidOutcome::Transitioned) {
            Mail::to($order->customer_email)->queue(new OrderConfirmation($order->fresh()));

            if ($overRedeemedCode) {
                Mail::to(config('shop.operator_email'))->queue(new PaymentAnomaly(
                    'Discount code redeemed past its usage limit',
                    $order->order_number,
                ));
            }
        }

        return $outcome;
    }

    public function createFromCart(
        CartSnapshot $cart,
        CustomerDetails $customer,
        ShippingQuote $shipping,
        ?DiscountResult $discount = null,
    ): Order {
        if ($cart->isEmpty()) {
            throw new InsufficientStockException('Your cart is empty.');
        }

        return DB::transaction(function () use ($cart, $customer, $shipping, $discount) {
            $subtotal = $cart->subtotalMinor();
            $discountMinor = $discount?->amountMinor ?? 0;

            $order = Order::create([
                'order_number' => $this->nextOrderNumber(),
                'status' => OrderStatus::PendingPayment,
                'source' => 'web',
                'customer_email' => $customer->email,
                'customer_name' => $customer->name,
                'phone' => $customer->phone,
                'address_line1' => $customer->addressLine1,
                'address_line2' => $customer->addressLine2,
                'city' => $customer->city,
                'postcode' => $customer->postcode,
                'country_code' => strtoupper($customer->countryCode),
                'subtotal_minor' => $subtotal,
                'shipping_minor' => $shipping->priceMinor,
                'discount_minor' => $discountMinor,
                'total_minor' => $subtotal - $discountMinor + $shipping->priceMinor,
                'currency' => 'AZN',
                'discount_code_id' => $discount?->codeId,
                'shipping_rate_id' => $shipping->rateId,
                'total_weight_grams' => $cart->totalWeightGrams(),
                'customs_contents' => 'Hand-crafted leather goods',
                'customs_value_minor' => $subtotal,
                'reserved_until' => now()->addMinutes(self::RESERVATION_MINUTES),
            ]);

            foreach ($cart->lines as $line) {
                $this->reserveStock($line);

                OrderItem::create([
                    'order_id' => $order->id,
                    'variant_id' => $line->variantId,
                    'product_name' => $line->productName,
                    'variant_description' => $line->variantDescription,
                    'sku' => Variant::whereKey($line->variantId)->value('sku') ?? '',
                    'unit_price_minor' => $line->unitPriceMinor,
                    'quantity' => $line->quantity,
                    'line_total_minor' => $line->lineTotalMinor(),
                    'personalization' => $line->personalization,
                    'weight_grams' => $line->weightGrams,
                ]);
            }

            return $order->load('items');
        });
    }

    private function reserveStock(CartLine $line): void
    {
        $variant = Variant::whereKey($line->variantId)->lockForUpdate()->first();

        if (! $variant || $variant->stock_quantity < $line->quantity) {
            throw new InsufficientStockException(
                "{$line->productName} ({$line->variantDescription}) is no longer available in that quantity."
            );
        }

        $variant->decrement('stock_quantity', $line->quantity);
    }

    private function nextOrderNumber(): string
    {
        return 'LS-' . now()->format('Y') . '-' . Str::upper(Str::random(6));
    }
}
