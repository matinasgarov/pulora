<?php // tests/Unit/Support/HasTranslationsTest.php

use App\Domain\Catalog\Models\Product;

it('returns the value for the active locale', function () {
    $product = new Product(['name' => ['en' => 'Bifold wallet', 'az' => 'İkiqat pulqabı']]);

    app()->setLocale('en');
    expect($product->name)->toBe('Bifold wallet');

    app()->setLocale('az');
    expect($product->name)->toBe('İkiqat pulqabı');
});

it('falls back to the default locale when the active one is empty', function () {
    $product = new Product(['name' => ['en' => 'Bifold wallet', 'az' => '']]);

    app()->setLocale('az');

    expect($product->name)->toBe('Bifold wallet');
});

it('falls back when the active locale key is missing entirely', function () {
    $product = new Product(['name' => ['en' => 'Bifold wallet']]);

    app()->setLocale('az');

    expect($product->name)->toBe('Bifold wallet');
});

// Every pre-existing factory and test writes a bare string. If this breaks,
// roughly forty tests from Plan 1 and Plan 2A break with it.
it('passes a plain string through untouched', function () {
    $product = new Product(['name' => 'Bifold wallet']);

    app()->setLocale('az');

    expect($product->name)->toBe('Bifold wallet');
});

it('returns an empty string rather than null when nothing is set', function () {
    $product = new Product;

    expect($product->name)->toBe('');
});

it('exposes the raw per-locale array', function () {
    $product = new Product(['name' => ['en' => 'Bifold wallet', 'az' => 'İkiqat pulqabı']]);

    expect($product->getTranslations('name'))
        ->toBe(['en' => 'Bifold wallet', 'az' => 'İkiqat pulqabı']);
});

it('wraps a plain string when the raw array is requested', function () {
    $product = new Product(['name' => 'Bifold wallet']);

    expect($product->getTranslations('name'))->toBe(['en' => 'Bifold wallet']);
});

it('sets a single locale without disturbing the other', function () {
    $product = new Product(['name' => ['en' => 'Bifold wallet']]);

    $product->setTranslation('name', 'az', 'İkiqat pulqabı');

    expect($product->getTranslations('name'))
        ->toBe(['en' => 'Bifold wallet', 'az' => 'İkiqat pulqabı']);
});

it('leaves non-translatable attributes alone', function () {
    $product = new Product(['slug' => 'bifold-wallet']);

    expect($product->slug)->toBe('bifold-wallet');
});
