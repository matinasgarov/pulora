<?php // app/Domain/Order/OrderService.php

namespace App\Domain\Order;

use App\Domain\Cart\CartLine;
use App\Domain\Cart\CartSnapshot;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Discount\DiscountResult;
use App\Domain\Discount\DiscountService;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderEvent;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Payment\Models\PaymentLog;
use App\Domain\Payment\PaymentGateway;
use App\Domain\Shipping\ShippingQuote;
use App\Mail\OrderConfirmation;
use App\Mail\PaymentAnomaly;
use App\Mail\ShipmentNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

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
        ?string $locale = null,
    ): Order {
        if ($cart->isEmpty()) {
            throw new InsufficientStockException('Your cart is empty.');
        }

        return DB::transaction(function () use ($cart, $customer, $shipping, $discount, $locale) {
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
                'locale' => $locale,
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

    /**
     * The single guarded entry point for every operator-driven status change.
     * Nothing else in the application may write orders.status.
     */
    public function transition(
        Order $order,
        OrderStatus $to,
        ?string $note = null,
        ?int $userId = null,
        bool $restoreCapacity = false,
        ?string $trackingNumber = null,
    ): void {
        if ($to === OrderStatus::Shipped && blank($trackingNumber)) {
            throw new InvalidArgumentException('A tracking number is required to mark an order shipped.');
        }

        // Pre-flight legality check, ahead of the (possibly money-moving) gateway
        // call below. This is not the source of truth — the locked re-check inside
        // the transaction is — but without it a refund on an already-refunded order
        // would hit the gateway and log a second real refund before the illegal
        // transition is ever detected.
        if (! $order->status->canTransitionTo($to)) {
            throw IllegalTransitionException::between($order->status, $to);
        }

        // The gateway call happens outside the transaction: a refund that
        // succeeds at the bank but fails locally is recoverable, whereas a
        // transaction held open across a network call is not.
        $refund = null;

        if ($to === OrderStatus::Refunded) {
            $refund = app(PaymentGateway::class)->refund($order, $order->total_minor);

            PaymentLog::create([
                'order_id' => $order->id,
                'gateway' => app(PaymentGateway::class)->gatewayName(),
                'direction' => 'refund',
                'reference' => $refund->reference,
                'payload' => ['succeeded' => $refund->succeeded, 'amount_minor' => $order->total_minor],
            ]);

            if (! $refund->succeeded) {
                throw new RuntimeException(
                    "The payment provider refused the refund (reference {$refund->reference}). The order is unchanged."
                );
            }
        }

        DB::transaction(function () use ($order, $to, $note, $userId, $restoreCapacity, $trackingNumber) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->status->canTransitionTo($to)) {
                throw IllegalTransitionException::between($locked?->status ?? $order->status, $to);
            }

            $from = $locked->status;

            $attributes = ['status' => $to];

            if ($to === OrderStatus::Shipped) {
                $attributes['tracking_number'] = $trackingNumber;
                $attributes['shipped_at'] = now();
            }

            $locked->update($attributes);

            if ($to === OrderStatus::Cancelled || ($to === OrderStatus::Refunded && $restoreCapacity)) {
                $this->restoreCapacity($locked);
            }

            OrderEvent::create([
                'order_id' => $locked->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'note' => $note,
                'user_id' => $userId,
                'created_at' => now(),
            ]);
        });

        // The passed-in $order instance still holds pre-transaction attributes
        // (Eloquent doesn't mutate the caller's copy from inside the closure).
        // Refresh it so callers who hold onto $order — Filament actions among
        // them — see the new status without having to know to call fresh().
        $order->refresh();

        if ($to === OrderStatus::Shipped) {
            Mail::to($order->customer_email)->queue(new ShipmentNotification($order));
        }
    }

    /**
     * "Made, not yet posted." A workshop bookkeeping mark, not a status change —
     * the domain transition to Shipped still happens once, at the post office.
     * Deliberately does not write an OrderEvent: this is bookkeeping, not a
     * status transition, so it is omitted from the audit trail by design.
     */
    public function markReady(Order $order): void
    {
        if (! in_array($order->status, [OrderStatus::Paid, OrderStatus::InProduction], true)) {
            throw new IllegalTransitionException(
                "An order in status {$order->status->label()} cannot be marked ready."
            );
        }

        $order->update(['ready_at' => now()]);
    }

    private function restoreCapacity(Order $order): void
    {
        foreach ($order->items as $item) {
            if (! $item->variant_id) {
                continue;
            }

            $variant = Variant::whereKey($item->variant_id)->lockForUpdate()->first();

            $variant?->increment('stock_quantity', $item->quantity);
        }
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
