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
}
