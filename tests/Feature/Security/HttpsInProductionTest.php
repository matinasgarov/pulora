<?php // tests/Feature/Security/HttpsInProductionTest.php

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;

/**
 * Re-runs the provider's boot under a pretended environment.
 *
 * forceScheme is global and sticky, so each test puts it back — otherwise the
 * first one to force https would quietly rewrite every URL asserted on by every
 * test that ran after it.
 */
function bootAppProviderAs(string $environment): void
{
    app()['env'] = $environment;
    (new AppServiceProvider(app()))->boot();
}

afterEach(function () {
    URL::forceScheme('http');
    app()['env'] = 'testing';
    config(['session.secure' => null]);
});

it('marks the session cookie Secure in production', function () {
    // Without this the browser sends the operator's session cookie over plain
    // HTTP, where anyone on the same network can lift it and be the owner.
    // config/session.php reads SESSION_SECURE_COOKIE, which nothing sets.
    expect(config('session.secure'))->toBeNull();

    bootAppProviderAs('production');

    expect(config('session.secure'))->toBeTrue();
});

it('generates https URLs in production', function () {
    // One http:// form action or asset on an otherwise https page is enough to
    // leak the cookie. Behind a TLS-terminating proxy the request itself
    // reports http, so the scheme has to be forced rather than detected.
    bootAppProviderAs('production');

    expect(URL::to('/admin'))->toStartWith('https://')
        ->and(route('storefront.catalogue', ['locale' => 'az']))->toStartWith('https://');
});

it('leaves local development on http', function () {
    // artisan serve speaks http. Forcing https there would break every link,
    // and a Secure cookie would log you out on each request.
    bootAppProviderAs('local');

    expect(config('session.secure'))->toBeNull()
        ->and(URL::to('/admin'))->toStartWith('http://');
});
