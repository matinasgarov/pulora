<?php // app/Http/Controllers/CheckoutConfirmationController.php

namespace App\Http\Controllers;

use App\Domain\Cart\CartService;
use App\Domain\Order\Models\Order;
use App\Domain\Order\OrderStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;

class CheckoutConfirmationController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function __invoke(): View|RedirectResponse
    {
        // Same reasoning as OrderLookupController::show() — this route is
        // outside the {locale} prefix, but the layout it renders links back
        // into locale-prefixed routes.
        URL::defaults(['locale' => app()->getLocale()]);

        // The cart is only cleared once the order it belongs to is actually paid —
        // an abandoned or failed payment must leave the customer's basket intact
        // (spec §5). session('last_order_number') is set at redirect time but the
        // order's status is what we trust here, not the session alone.
        $orderNumber = session('last_order_number');
        $order = $orderNumber ? Order::where('order_number', $orderNumber)->first() : null;

        // Nothing to confirm: a visitor landing here without having just
        // checked out (no session, or a session pointing at no known order)
        // gets sent back to the shop rather than a confirmation page with
        // nothing to show.
        if (! $order) {
            return redirect('/'.config('app.locale'));
        }

        if ($order->status === OrderStatus::Paid) {
            $this->cart->clear();
        }

        return view('checkout.confirmation');
    }
}
