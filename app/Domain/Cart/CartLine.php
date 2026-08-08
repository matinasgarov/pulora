<?php // app/Domain/Cart/CartLine.php

namespace App\Domain\Cart;

final readonly class CartLine
{
    public function __construct(
        public string $lineKey,
        public int $variantId,
        public int $quantity,
        public string $productName,
        public string $variantDescription,
        public int $unitPriceMinor,
        public array $personalization,
        public int $weightGrams,
    ) {}

    public function lineTotalMinor(): int
    {
        return $this->unitPriceMinor * $this->quantity;
    }
}
