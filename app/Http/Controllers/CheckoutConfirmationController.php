<?php // app/Http/Controllers/CheckoutConfirmationController.php

namespace App\Http\Controllers;

use App\Domain\Cart\CartService;
use App\Domain\Order\Models\Order;
use App\Domain\Order\OrderStatus;
use Illuminate\Contracts\View\View;

class CheckoutConfirmationController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function __invoke(): View
    {
        // The cart is only cleared once the order it belongs to is actually paid —
        // an abandoned or failed payment must leave the customer's basket intact
        // (spec §5). session('last_order_number') is set at redirect time but the
        // order's status is what we trust here, not the session alone.
        $orderNumber = session('last_order_number');

        if ($orderNumber) {
            $order = Order::where('order_number', $orderNumber)->first();

            if ($order && $order->status === OrderStatus::Paid) {
                $this->cart->clear();
            }
        }

        return view('checkout.confirmation');
    }
}
