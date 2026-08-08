<?php // tests/Feature/Discount/DiscountServiceTest.php

use App\Domain\Discount\InvalidDiscountException;
use App\Domain\Discount\Models\DiscountCode;
use App\Domain\Discount\DiscountService;

beforeEach(fn () => $this->service = app(DiscountService::class));

function makeCode(array $overrides = []): DiscountCode
{
    return DiscountCode::create(array_merge([
        'code' => 'LEATHER10', 'kind' => 'percent', 'value' => 10,
        'minimum_order_minor' => 0, 'usage_limit' => null, 'times_used' => 0,
        'expires_at' => null, 'is_active' => true,
    ], $overrides));
}

it('computes a percentage discount', function () {
    makeCode();
    expect($this->service->apply('LEATHER10', 10000)->amountMinor)->toBe(1000);
});

it('matches codes case-insensitively', function () {
    makeCode();
    expect($this->service->apply('leather10', 10000)->amountMinor)->toBe(1000);
});

it('computes a fixed discount', function () {
    makeCode(['code' => 'FIVER', 'kind' => 'fixed', 'value' => 500]);
    expect($this->service->apply('FIVER', 10000)->amountMinor)->toBe(500);
});

it('never discounts more than the subtotal', function () {
    makeCode(['code' => 'BIG', 'kind' => 'fixed', 'value' => 99999]);
    expect($this->service->apply('BIG', 10000)->amountMinor)->toBe(10000);
});

it('rejects a code below its minimum order', function () {
    makeCode(['minimum_order_minor' => 20000]);
    $this->service->apply('LEATHER10', 10000);
})->throws(InvalidDiscountException::class, 'This code requires a minimum order of 200.00 AZN.');

it('rejects an expired code', function () {
    makeCode(['expires_at' => now()->subDay()]);
    $this->service->apply('LEATHER10', 10000);
})->throws(InvalidDiscountException::class);

it('rejects a code that reached its usage limit', function () {
    makeCode(['usage_limit' => 2, 'times_used' => 2]);
    $this->service->apply('LEATHER10', 10000);
})->throws(InvalidDiscountException::class);

it('rejects an unknown code', function () {
    $this->service->apply('NOPE', 10000);
})->throws(InvalidDiscountException::class);

it('increments usage and reports success while under the limit', function () {
    $code = makeCode(['usage_limit' => 2, 'times_used' => 0]);

    expect($this->service->consume($code->id))->toBeTrue();
    expect($code->fresh()->times_used)->toBe(1);
});

it('always increments a code with no usage limit', function () {
    $code = makeCode(['usage_limit' => null, 'times_used' => 500]);

    expect($this->service->consume($code->id))->toBeTrue();
    expect($code->fresh()->times_used)->toBe(501);
});

it('refuses to increment past the usage limit and reports failure', function () {
    $code = makeCode(['usage_limit' => 2, 'times_used' => 2]);

    expect($this->service->consume($code->id))->toBeFalse();
    expect($code->fresh()->times_used)->toBe(2);
});
