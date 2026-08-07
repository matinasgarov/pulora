<?php // tests/Unit/Domain/MoneyTest.php

use App\Domain\Money;

it('formats minor units as a decimal string', function () {
    expect(Money::format(12345))->toBe('123.45 AZN');
    expect(Money::format(5))->toBe('0.05 AZN');
    expect(Money::format(0))->toBe('0.00 AZN');
});

it('rounds percentages half up to whole minor units', function () {
    expect(Money::percentOf(10000, 10))->toBe(1000);
    // 333 * 10% = 33.3 -> 33, not 34, and never 33.300000000000004
    expect(Money::percentOf(333, 10))->toBe(33);
    // 335 * 10% = 33.5 -> half up -> 34
    expect(Money::percentOf(335, 10))->toBe(34);
});
