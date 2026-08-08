<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/payment/mock/{reference}', function (string $reference) {
    return response()->view('payment.mock', ['reference' => $reference]);
})->name('payment.mock.form');
