<?php

use App\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/payment/mock/{reference}', function (string $reference) {
    return response()->view('payment.mock', ['reference' => $reference]);
})->name('payment.mock.form');

Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::view('/checkout/confirmation', 'checkout.confirmation')->name('checkout.confirmation');
