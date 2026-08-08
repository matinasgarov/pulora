<?php // app/Domain/Payment/RefundResult.php

namespace App\Domain\Payment;

final readonly class RefundResult
{
    public function __construct(public bool $succeeded, public string $reference) {}
}
