<?php // tests/Unit/Support/MoneyInputTest.php

use App\Support\MoneyInput;

it('converts manats to qepik without floating point', function () {
    expect(MoneyInput::toMinor('49.99'))->toBe(4999)
        ->and(MoneyInput::toMinor('0.01'))->toBe(1)
        ->and(MoneyInput::toMinor('100'))->toBe(10000)
        ->and(MoneyInput::toMinor('100.5'))->toBe(10050)
        ->and(MoneyInput::toMinor('1234.56'))->toBe(123456);
});

it('accepts a comma as the decimal separator', function () {
    expect(MoneyInput::toMinor('49,99'))->toBe(4999);
});

it('rounds a third decimal place rather than truncating it', function () {
    expect(MoneyInput::toMinor('1.005'))->toBe(101)
        ->and(MoneyInput::toMinor('1.004'))->toBe(100);
});

it('passes null through', function () {
    expect(MoneyInput::toMinor(null))->toBeNull()
        ->and(MoneyInput::toMinor(''))->toBeNull()
        ->and(MoneyInput::toManats(null))->toBeNull();
});

it('converts qepik back to a two-decimal string', function () {
    expect(MoneyInput::toManats(4999))->toBe('49.99')
        ->and(MoneyInput::toManats(10000))->toBe('100.00')
        ->and(MoneyInput::toManats(1))->toBe('0.01')
        ->and(MoneyInput::toManats(0))->toBe('0.00');
});

it('round-trips every value without drift', function () {
    foreach ([1, 99, 100, 4999, 10000, 123456, 999999] as $minor) {
        expect(MoneyInput::toMinor(MoneyInput::toManats($minor)))->toBe($minor);
    }
});

it('builds a price field carrying the conversion', function () {
    $field = MoneyInput::field('base_price_minor');

    expect($field)->toBeInstanceOf(\Filament\Forms\Components\TextInput::class)
        ->and($field->getName())->toBe('base_price_minor');
});
