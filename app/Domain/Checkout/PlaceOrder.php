<?php // app/Domain/Checkout/PlaceOrder.php

namespace App\Domain\Checkout;

use App\Domain\Cart\CartService;
use App\Domain\Discount\DiscountService;
use App\Domain\Discount\InvalidDiscountException;
use App\Domain\Order\CustomerDetails;
use App\Domain\Order\InsufficientStockException;
use App\Domain\Order\OrderService;
use App\Domain\Payment\PaymentGateway;
use App\Domain\Shipping\NoShippingRateException;
use App\Domain\Shipping\ShippingCalculator;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The single write path for placing an order.
 *
 * Two entry points need this behaviour — the POST /checkout route kept from
 * Plan 1, and the Livewire checkout form — and neither may own a second copy
 * of it. Same reasoning as TransitionActions funnelling every admin status
 * change through OrderService::transition().
 */
class PlaceOrder
{
    public function __construct(
        private CartService $cart,
        private ShippingCalculator $shipping,
        private DiscountService $discounts,
        private OrderService $orders,
        private PaymentGateway $gateway,
    ) {}

    public function __invoke(
        CustomerDetails $customer,
        int $shippingRateId,
        ?string $discountCode,
        string $locale,
    ): PlaceOrderResult {
        $snapshot = $this->cart->snapshot();

        if ($snapshot->isEmpty()) {
            return PlaceOrderResult::failure('cart', 'Your cart is empty.');
        }

        try {
            $quote = $this->shipping->quoteById(
                $shippingRateId,
                $customer->countryCode,
                $snapshot->totalWeightGrams(),
            );
        } catch (NoShippingRateException) {
            return PlaceOrderResult::failure(
                'shipping_rate_id',
                'That shipping option is not available for your address.',
            );
        }

        $discount = null;
        if ($discountCode) {
            try {
                $discount = $this->discounts->apply($discountCode, $snapshot->subtotalMinor());
            } catch (InvalidDiscountException $e) {
                return PlaceOrderResult::failure('discount_code', $e->getMessage());
            }
        }

        try {
            $order = $this->orders->createFromCart($snapshot, $customer, $quote, $discount, $locale);
        } catch (InsufficientStockException $e) {
            return PlaceOrderResult::failure('cart', $e->getMessage());
        }

        // Persisted before the gateway call: a fast gateway can call back before
        // createPayment() even returns, and the callback resolves orders solely by
        // payment_reference. If we wrote it only after the call, a real payment could
        // 404. MockGateway derives the reference deterministically from order_number,
        // so we can compute and store it up front.
        $order->update(['payment_reference' => 'MOCK-' . $order->order_number]);

        try {
            $redirect = $this->gateway->createPayment($order);
        } catch (Throwable $e) {
            // The order is already reserved and saved. Leave it pending — Task 14's
            // expiry job returns the stock if the customer never comes back — and keep
            // the cart so retrying costs them nothing.
            Log::error('Payment gateway could not be reached', [
                'order_number' => $order->order_number,
                'exception' => $e->getMessage(),
            ]);

            $order->update(['payment_reference' => null]);

            return PlaceOrderResult::failure(
                'payment',
                "We could not reach the payment provider. Your order {$order->order_number} is saved but unpaid — please try again in a moment.",
            );
        }

        // The cart is preserved until the order is confirmed paid (spec §5): an
        // abandoned or failed payment should not cost the customer their basket.
        // It is cleared on the confirmation page once the order is actually Paid.
        session(['last_order_number' => $order->order_number]);

        return PlaceOrderResult::success($redirect->url);
    }
}
