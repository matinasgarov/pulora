<?php // app/Domain/Payment/CallbackResult.php

namespace App\Domain\Payment;

final readonly class CallbackResult
{
    public function __construct(
        public bool $isValid,
        public string $reference,
        public bool $isPaid,
        public array $raw = [],
    ) {}
}
