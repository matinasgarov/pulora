<?php

use App\Domain\Order\Models\Order;
use App\Http\Controllers\CheckoutConfirmationController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderLookupController;
use App\Http\Controllers\PaymentCallbackController;
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
