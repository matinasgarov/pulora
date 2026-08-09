<?php // tests/Unit/Cart/PersonalizationValidatorTest.php

use App\Domain\Catalog\Models\PersonalizationOption;
use App\Domain\Catalog\Models\Product;
use App\Domain\Cart\InvalidPersonalizationException;
use App\Domain\Cart\PersonalizationValidator;

beforeEach(function () {
    $this->product = Product::factory()->create();
    PersonalizationOption::create([
        'product_id' => $this->product->id,
        'type' => 'monogram',
        'label' => 'Monogram',
        'price_delta_minor' => 1000,
        'max_characters' => 3,
        'allowed_pattern' => '/^[A-Z]+$/',
        'is_required' => false,
    ]);
    $this->validator = new PersonalizationValidator();
});

it('uppercases and accepts a valid monogram', function () {
    expect($this->validator->validate($this->product, ['monogram' => 'ma']))
        ->toBe(['monogram' => 'MA']);
});

it('rejects a monogram longer than the product allows', function () {
    $this->validator->validate($this->product, ['monogram' => 'ABCD']);
})->throws(InvalidPersonalizationException::class, 'Monogram must be at most 3 characters.');

it('rejects characters outside the allowed pattern', function () {
    $this->validator->validate($this->product, ['monogram' => 'A1']);
})->throws(InvalidPersonalizationException::class);

it('ignores personalization the product does not offer', function () {
    expect($this->validator->validate($this->product, ['gift_wrap' => 'yes']))->toBe([]);
});

it('rejects a missing required option', function () {
    PersonalizationOption::where('product_id', $this->product->id)->update(['is_required' => true]);
    $this->product->refresh()->load('personalizationOptions');

    $this->validator->validate($this->product, []);
})->throws(InvalidPersonalizationException::class, 'Monogram is required.');
