<?php // tests/Feature/Payment/PaymentGatewayBindingTest.php

use App\Domain\Payment\PaymentGateway;

// Routes are registered once at application boot time (governed by phpunit.xml's
// forced APP_ENV=testing), so simulating "the mock route is 404 outside
// local/testing" would require booting a second application under a different
// environment — not practical inside this test run. Instead we verify the
// underlying guard directly: the container binding itself refuses to hand out
// MockGateway once the environment is not local/testing, which is the same
// guard the route registration in routes/web.php relies on.
it('refuses to resolve the mock payment gateway outside local/testing environments', function () {
    app()['env'] = 'production';

    expect(fn () => app(PaymentGateway::class))->toThrow(RuntimeException::class);
});

it('throws a clear error when no payment driver is configured', function () {
    config(['services.payment.driver' => null]);

    expect(fn () => app(PaymentGateway::class))->toThrow(RuntimeException::class);
});
