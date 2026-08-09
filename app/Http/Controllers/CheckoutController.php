<?php // app/Http/Controllers/CheckoutController.php

namespace App\Http\Controllers;

use App\Domain\Cart\CartService;
use App\Domain\Discount\DiscountService;
use App\Domain\Discount\InvalidDiscountException;
use App\Domain\Order\CustomerDetails;
use App\Domain\Order\InsufficientStockException;
use App\Domain\Order\OrderService;
use App\Domain\Payment\PaymentGateway;
use App\Domain\Shipping\NoShippingRateException;
use App\Domain\Shipping\ShippingCalculator;
use App\Http\Requests\CheckoutRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cart,
        private ShippingCalculator $shipping,
        private DiscountService $discounts,
        private OrderService $orders,
        private PaymentGateway $gateway,
    ) {}

    public function store(CheckoutRequest $request): RedirectResponse
    {
        $snapshot = $this->cart->snapshot();

        if ($snapshot->isEmpty()) {
            return back()->withErrors(['cart' => 'Your cart is empty.'])->withInput();
        }

        try {
            $quote = $this->shipping->quoteById(
                (int) $request->validated('shipping_rate_id'),
                $request->validated('country_code'),
                $snapshot->totalWeightGrams(),
            );
        } catch (NoShippingRateException) {
            return back()->withErrors([
                'shipping_rate_id' => 'That shipping option is not available for your address.',
            ])->withInput();
        }

        $discount = null;
        if ($code = $request->validated('discount_code')) {
            try {
                $discount = $this->discounts->apply($code, $snapshot->subtotalMinor());
            } catch (InvalidDiscountException $e) {
                return back()->withErrors(['discount_code' => $e->getMessage()])->withInput();
            }
        }

        $customer = new CustomerDetails(
            email: $request->validated('email'),
            name: $request->validated('name'),
            addressLine1: $request->validated('address_line1'),
            addressLine2: $request->validated('address_line2'),
            city: $request->validated('city'),
            postcode: $request->validated('postcode'),
            countryCode: $request->validated('country_code'),
            phone: $request->validated('phone'),
        );

        try {
            $order = $this->orders->createFromCart($snapshot, $customer, $quote, $discount);
        } catch (InsufficientStockException $e) {
            return back()->withErrors(['cart' => $e->getMessage()])->withInput();
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

            return back()->withErrors([
                'payment' => "We could not reach the payment provider. Your order {$order->order_number} is saved but unpaid — please try again in a moment.",
            ])->withInput();
        }

        // The cart is preserved until the order is confirmed paid (spec §5): an
        // abandoned or failed payment should not cost the customer their basket.
        // It is cleared on the confirmation page once the order is actually Paid.
        session(['last_order_number' => $order->order_number]);

        return redirect()->away($redirect->url);
    }
}
