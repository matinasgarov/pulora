<?php // tests/Feature/Storefront/CheckoutHardeningTest.php

use App\Models\User;
use Illuminate\Support\Facades\Route;

it('throttles checkout submissions', function () {
    // The discount_code field is validated on submit and the rejection messages
    // distinguish invalid from expired from exhausted, so unlimited attempts
    // amount to an oracle for brute-forcing a working code.
    $middleware = collect(Route::getRoutes()->getByName('checkout.store')->gatherMiddleware());

    expect($middleware)->toContain('throttle:20,1');
});

it('seeds no account with a known password', function () {
    $this->seed(\Database\Seeders\DatabaseSeeder::class);

    expect(User::query()->count())->toBe(0);
});
