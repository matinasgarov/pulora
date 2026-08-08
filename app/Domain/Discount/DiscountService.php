<?php // app/Domain/Discount/DiscountService.php

namespace App\Domain\Discount;

use App\Domain\Discount\Models\DiscountCode;
use App\Domain\Money;

class DiscountService
{
    public function apply(string $code, int $subtotalMinor): DiscountResult
    {
        $discount = DiscountCode::whereRaw('UPPER(code) = ?', [mb_strtoupper(trim($code))])
            ->where('is_active', true)
            ->first();

        if (! $discount) {
            throw new InvalidDiscountException('That discount code is not valid.');
        }

        if ($discount->expires_at && $discount->expires_at->isPast()) {
            throw new InvalidDiscountException('That discount code has expired.');
        }

        if ($discount->usage_limit !== null && $discount->times_used >= $discount->usage_limit) {
            throw new InvalidDiscountException('That discount code has already been fully used.');
        }

        if ($subtotalMinor < $discount->minimum_order_minor) {
            throw new InvalidDiscountException(
                'This code requires a minimum order of ' . Money::format($discount->minimum_order_minor) . '.'
            );
        }

        $amount = $discount->kind === 'percent'
            ? Money::percentOf($subtotalMinor, $discount->value)
            : $discount->value;

        return new DiscountResult($discount->id, $discount->code, min($amount, $subtotalMinor));
    }

    public function consume(int $codeId): void
    {
        DiscountCode::where('id', $codeId)->increment('times_used');
    }
}
