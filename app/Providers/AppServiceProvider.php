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
            return match (config('services.payment.driver')) {
                'mock' => new \App\Domain\Payment\MockGateway(config('services.payment.mock_secret')),
                default => throw new \RuntimeException(
                    'Unknown payment driver: ' . config('services.payment.driver')
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
