<?php // tests/Unit/ShopConfigTest.php

it('keeps an operator address for payment anomaly mail', function () {
    // Every test that uses this sets it explicitly with config([...]), so none
    // of them read the real value — deleting the key from config/shop.php left
    // the whole suite green while payment anomaly mail would have gone to
    // nobody in production. This is the only assertion that would have failed.
    expect(config('shop.operator_email'))->toBeString()->not->toBeEmpty();
});

it('leaves ordering enabled unless something turns it off', function () {
    expect(config('shop.ordering'))->toBeTrue();
});
