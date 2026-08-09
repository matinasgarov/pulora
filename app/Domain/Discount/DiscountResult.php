<?php // app/Domain/Discount/DiscountResult.php

namespace App\Domain\Discount;

final readonly class DiscountResult
{
    public function __construct(
        public int $codeId,
        public string $code,
        public int $amountMinor,
    ) {}
}
