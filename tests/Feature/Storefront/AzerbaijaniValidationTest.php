<?php // tests/Feature/Storefront/AzerbaijaniValidationTest.php

it('speaks Azerbaijani when the Azerbaijani checkout rejects a field', function () {
    app()->setLocale('az');

    $message = __('validation.required', ['attribute' => __('validation.attributes.city')]);

    expect($message)->toBe('Şəhər sahəsi tələb olunur.')
        ->and($message)->not->toContain('field is required');
});

it('names fields as a customer would, not as the database does', function () {
    app()->setLocale('az');

    // Without an attributes entry this reads "address_line1", which is a column
    // name shown to a customer.
    expect(__('validation.attributes.address_line1'))->toBe('Ünvan')
        ->and(__('validation.attributes.shipping_rate_id'))->toBe('Çatdırılma üsulu');
});

it('falls back to a real sentence for an untranslated rule', function () {
    // The file covers the rules in use, not the framework's full list. Anything
    // missing must still produce English prose rather than a raw key.
    app()->setLocale('az');

    expect(__('validation.uploaded', ['attribute' => 'x']))->not->toStartWith('validation.');
});

it('returns Azerbaijani errors from a real checkout submission', function () {
    // End-to-end through the actual FormRequest, which is where the English
    // "The city field is required." was surfacing.
    $this->withHeader('Accept-Language', 'az')
        ->post('/checkout', ['country_code' => 'AZ'])
        ->assertSessionHasErrors(['city' => 'Şəhər sahəsi tələb olunur.']);
});
