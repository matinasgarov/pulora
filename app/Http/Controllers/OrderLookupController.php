<?php // app/Http/Controllers/OrderLookupController.php

namespace App\Http\Controllers;

use App\Domain\Order\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class OrderLookupController extends Controller
{
    public function show(): View
    {
        // This route lives outside the {locale} prefix (an order lookup is not
        // tied to a language segment), but the storefront layout it renders
        // links back into locale-prefixed routes via URL::defaults(). Without
        // this, route('storefront.catalogue') in the header has no locale to
        // fill in and throws.
        URL::defaults(['locale' => app()->getLocale()]);

        return view('orders.lookup');
    }

    public function find(Request $request): View|RedirectResponse
    {
        URL::defaults(['locale' => app()->getLocale()]);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'order_number' => ['required', 'string', 'max:64'],
        ]);

        // Case-insensitive match on both fields. On no match we show a message
        // that is deliberately identical whether the email is wrong, the order
        // number is wrong, or both — never reveal which, and never leak whether
        // an order number exists at all.
        $order = Order::whereRaw('LOWER(order_number) = ?', [strtolower($data['order_number'])])
            ->whereRaw('LOWER(customer_email) = ?', [strtolower($data['email'])])
            ->with('items')
            ->first();

        if (! $order) {
            return view('orders.lookup', ['notFound' => true]);
        }

        return view('orders.show', ['order' => $order]);
    }
}
