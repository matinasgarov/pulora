<?php

use App\Domain\Order\Models\Order;
use App\Http\Controllers\CheckoutConfirmationController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderLookupController;
use App\Http\Controllers\PaymentCallbackController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

if (app()->environment(['local', 'testing'])) {
    Route::get('/payment/mock/{reference}', function (string $reference) {
        $order = Order::where('payment_reference', $reference)->firstOrFail();

        return response()->view('payment.mock', [
            'reference' => $reference,
            'amountMinor' => $order->total_minor,
            'currency' => $order->currency,
        ]);
    })->name('payment.mock.form');
}

Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::post('/payment/callback', PaymentCallbackController::class)
    ->middleware('throttle:60,1')
    ->name('payment.callback');
Route::get('/checkout/confirmation', CheckoutConfirmationController::class)->name('checkout.confirmation');

Route::get('/orders/lookup', [OrderLookupController::class, 'show'])->name('orders.lookup');
Route::post('/orders/lookup', [OrderLookupController::class, 'find'])
    ->middleware('throttle:10,1')
    ->name('orders.lookup.find');

// Filament's admin login page is a Livewire component reachable only via GET;
// the panel exposes no way to attach middleware to just its login submission
// (->middleware() on the panel would throttle every admin request, and
// ->authMiddleware() only guards already-authenticated routes). This route
// is a plain HTTP fallback so that direct POSTs to /admin/login — the kind a
// scripted brute-force attempt would send — are rate limited and logged,
// independent of Filament's own Livewire-level throttling.
Route::post('/admin/login', function (Request $request) {
    $credentials = $request->only('email', 'password');

    if (Auth::attempt($credentials)) {
        $request->session()->regenerate();

        return redirect('/admin');
    }

    return redirect('/admin/login');
})->middleware('throttle:admin-login')->name('admin.login.throttled');
