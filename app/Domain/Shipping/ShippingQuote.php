<?php // app/Domain/Shipping/ShippingQuote.php

namespace App\Domain\Shipping;

final readonly class ShippingQuote
{
    public function __construct(
        public int $rateId,
        public string $name,
        public int $priceMinor,
    ) {}
}
