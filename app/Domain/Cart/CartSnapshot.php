<?php // app/Domain/Cart/CartSnapshot.php

namespace App\Domain\Cart;

final readonly class CartSnapshot
{
    /** @param CartLine[] $lines */
    public function __construct(public array $lines) {}

    public function subtotalMinor(): int
    {
        return array_sum(array_map(fn (CartLine $l) => $l->lineTotalMinor(), $this->lines));
    }

    /** Pieces in the bag, not lines — two of one thing is a bag of two. */
    public function totalQuantity(): int
    {
        return array_sum(array_map(fn (CartLine $l) => $l->quantity, $this->lines));
    }

    public function totalWeightGrams(): int
    {
        return array_sum(array_map(fn (CartLine $l) => $l->weightGrams * $l->quantity, $this->lines));
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }
}
