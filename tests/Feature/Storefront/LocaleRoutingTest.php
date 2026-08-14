<?php // tests/Feature/Storefront/LocaleRoutingTest.php

it('redirects the bare root to the default locale', function () {
    $this->get('/')->assertRedirect('/en');
});

it('serves the English catalogue', function () {
    $this->get('/en')->assertSuccessful();
});

it('serves the Azerbaijani catalogue', function () {
    $this->get('/az')->assertSuccessful();
});

it('sets the application locale from the url', function () {
    $this->get('/az');

    expect(app()->getLocale())->toBe('az');
});

it('rejects an unsupported locale', function () {
    $this->get('/de')->assertNotFound();
});

it('keeps the payment callback outside the locale prefix', function () {
    // The gateway posts to a fixed URL. Prefixing it would break every callback.
    expect(route('payment.callback'))->not->toContain('/en/')
        ->and(route('payment.callback'))->not->toContain('/az/');
});

it('keeps the checkout post route working', function () {
    expect(route('checkout.store'))->toContain('/checkout');
});

it('translates a key differently per locale', function () {
    app()->setLocale('en');
    $en = __('shop.nav.cart');

    app()->setLocale('az');
    $az = __('shop.nav.cart');

    expect($en)->toBe('Cart')
        ->and($az)->toBe('Səbət')
        ->and($en)->not->toBe($az);
});

it('does not swallow the admin panel', function () {
    $this->get('/admin/login')->assertSuccessful();
});

it('does not swallow the health check', function () {
    $this->get('/up')->assertSuccessful();
});

it('leaves the order lookup reachable', function () {
    $this->get('/orders/lookup')->assertSuccessful();
});

it('leaves the checkout confirmation reachable', function () {
    // Task 9 restyles this; it must not 404 in the meantime.
    $this->get('/checkout/confirmation')->assertRedirect();
});
