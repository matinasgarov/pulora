<?php

namespace App\Providers;

use App\Domain\Payment\MockGateway;
use App\Domain\Payment\PaymentGateway;
use Illuminate\Auth\Events\Failed;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, function ($app) {
            $driver = config('services.payment.driver');

            if ($driver === null || $driver === '') {
                throw new \RuntimeException(
                    'No payment driver configured. Set PAYMENT_DRIVER in your environment.'
                );
            }

            return match ($driver) {
                'mock' => $app->environment(['local', 'testing'])
                    ? new MockGateway(config('services.payment.mock_secret'))
                    : throw new \RuntimeException(
                        'The mock payment driver is not permitted outside local/testing environments.'
                    ),
                default => throw new \RuntimeException(
                    'Unknown payment driver: '.$driver
                ),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->enforceHttpsInProduction();

        RateLimiter::for('admin-login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Failed fires for any Auth::attempt() anywhere in the app, not only
        // the admin login form. Admin login is the only auth mechanism today,
        // so this is a no-op distinction now, but the message must not claim
        // more than it observed once a second caller exists — hence logging
        // the guard actually reported by the event instead of hardcoding
        // "admin".
        Event::listen(Failed::class, fn (Failed $event) => Log::warning('Failed login attempt', [
            'guard' => $event->guard,
            'email' => $event->credentials['email'] ?? null,
            'ip' => request()->ip(),
        ]));
    }

    /**
     * In production, every generated URL is https and the session cookie is
     * only ever sent over it.
     *
     * The cookie is the thing being protected. An operator's session cookie is
     * a bearer token for the whole admin panel — orders, customer addresses,
     * discount codes — and without the Secure flag the browser will send it
     * over plain HTTP, where anyone sharing a network can read it and become
     * the owner. `Secure` is what stops that, and it was off because
     * config/session.php reads SESSION_SECURE_COOKIE, which nothing sets.
     *
     * forceScheme covers the other half: one http:// asset, form action or
     * redirect on an otherwise https page is enough to leak the cookie, and
     * asset()/route() would happily emit one if a request arrived reporting
     * http — which is exactly what happens behind a proxy terminating TLS.
     *
     * This is the application's half of the job. The web server still has to
     * redirect http:// to https:// and send HSTS; nothing here can protect a
     * first request that arrives in the clear.
     *
     * Production-only on purpose: forcing https locally would break
     * `artisan serve`, which speaks http, and a Secure cookie there would log
     * you out on every request.
     */
    private function enforceHttpsInProduction(): void
    {
        if (! $this->app->isProduction()) {
            return;
        }

        URL::forceScheme('https');

        // SameSite stays 'lax' rather than 'strict'. Strict would drop the
        // session on any inbound link — an Instagram post, an emailed order
        // link — taking the customer's cart with it. Lax already blocks the
        // cross-site POST that matters, and CSRF tokens cover the rest.
        config(['session.secure' => true]);
    }
}
