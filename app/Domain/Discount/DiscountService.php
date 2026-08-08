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

    /**
     * Increment usage, but only while the code remains under its limit.
     *
     * Returns false when the limit was already reached — the caller has taken
     * payment by this point, so the order stands and the operator is alerted
     * rather than the customer being refused.
     */
    public function consume(int $codeId): bool
    {
        $affected = DiscountCode::where('id', $codeId)
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhereColumn('times_used', '<', 'usage_limit');
            })
            ->increment('times_used');

        return $affected > 0;
    }
}
