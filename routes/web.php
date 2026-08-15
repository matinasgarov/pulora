<?php

use App\Domain\Order\Models\Order;
use App\Http\Controllers\CheckoutConfirmationController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderLookupController;
use App\Http\Controllers\PaymentCallbackController;
use App\Http\Controllers\Storefront\CatalogueController;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/'.config('app.locale'));

// {locale} is a single-segment wildcard, so /{locale} has the same shape as
// GET /admin — Filament's Workshop page. Today /admin wins only because panel
// providers register before web.php loads, which nothing here enforces and no
// refactor is obliged to preserve. Losing that race would route /admin into
// SetLocale, which would abort 404 and take the whole admin panel down.
// Constraining the parameter means the group cannot match /admin at all, so
// the ordering stops mattering. SetLocale still validates too — defence in
// depth, and it keeps working for any route registered outside this group.
Route::prefix('{locale}')
    ->whereIn('locale', SetLocale::SUPPORTED)
    ->middleware('setlocale')
    ->name('storefront.')
    ->group(function () {
        // Tasks 6-8 replace each remaining closure with its controller.
        Route::get('/', CatalogueController::class)->name('catalogue');
        Route::get('/product/{slug}', fn () => response('product'))->name('product');
        Route::get('/cart', fn () => response('cart'))->name('cart');
        Route::get('/checkout', fn () => response('checkout'))->name('checkout');
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
//
// IMPORTANT — this is a *secondary*, narrower defense, not the primary one:
// the real login form submits through Livewire's shared /livewire/update
// endpoint, not this route, so a real attacker using the actual admin UI
// never touches the throttle below. Filament's Login page already ships its
// own independent rate limiter (WithRateLimiting trait in
// vendor/filament/filament/src/Auth/Pages/Login.php) that is what actually
// protects the real UI today, and this route does not change or improve
// that.
//
// What this route actually covers is narrower than "any scripted POST":
// ValidateCsrfToken runs ahead of ThrottleRequests:admin-login in Laravel's
// default `web` middleware group, so a bare scripted POST with no prior page
// visit has no CSRF token and is rejected 419 before it ever reaches the
// throttle, Auth::attempt, or the Failed listener below — that case never
// needed this route. What this route/throttle actually guards against is a
// scripted client that first obtains a valid CSRF token (e.g. by GETing the
// login page, or by reusing/scraping a session cookie) and then POSTs
// repeated login attempts — CSRF alone does not stop that; the throttle
// does.
Route::post('/admin/login', function (Request $request) {
    $credentials = $request->only('email', 'password');

    // Mirror Filament's own gate (Login::authenticate() calls
    // attemptWhen($credentials, fn ($user) => $this->isUserAllowedToAccessPanel($user), ...))
    // so this fallback can never grant a session to a non-operator that the
    // real login page would have refused.
    //
    // Auth::attempt() already fires Illuminate\Auth\Events\Failed itself when
    // the credentials are simply wrong, so the AppServiceProvider listener
    // picks that case up for free. We only need to fire it ourselves for the
    // "valid credentials, but not an operator" case below, where Auth::attempt
    // succeeds and would not otherwise fire Failed.
    $credentialsValid = Auth::attempt($credentials);

    if ($credentialsValid && Auth::user()->is_operator) {
        $request->session()->regenerate();

        return redirect('/admin');
    }

    if ($credentialsValid) {
        // Valid credentials but not an operator: log the partial session back
        // out and manually fire Failed so this still counts toward
        // throttling/logging instead of silently leaving behind a
        // non-operator authenticated session.
        $user = Auth::user();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Event::dispatch(new Failed('web', $user, $credentials));
    }

    return redirect('/admin/login');
})->middleware('throttle:admin-login')->name('admin.login.throttled');
