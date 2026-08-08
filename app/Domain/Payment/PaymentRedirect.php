<?php // app/Domain/Payment/PaymentRedirect.php

namespace App\Domain\Payment;

final readonly class PaymentRedirect
{
    public function __construct(public string $url, public string $reference) {}
}
