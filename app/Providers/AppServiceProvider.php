<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Domain\Payment\PaymentGateway::class, function ($app) {
            $driver = config('services.payment.driver');

            if ($driver === null || $driver === '') {
                throw new \RuntimeException(
                    'No payment driver configured. Set PAYMENT_DRIVER in your environment.'
                );
            }

            return match ($driver) {
                'mock' => $app->environment(['local', 'testing'])
                    ? new \App\Domain\Payment\MockGateway(config('services.payment.mock_secret'))
                    : throw new \RuntimeException(
                        'The mock payment driver is not permitted outside local/testing environments.'
                    ),
                default => throw new \RuntimeException(
                    'Unknown payment driver: ' . $driver
                ),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
