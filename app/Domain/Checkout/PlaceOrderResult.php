<?php // app/Domain/Checkout/PlaceOrderResult.php

namespace App\Domain\Checkout;

final readonly class PlaceOrderResult
{
    public function __construct(
        public bool $succeeded,
        public ?string $redirectUrl = null,
        public ?string $errorField = null,
        public ?string $errorMessage = null,
    ) {}

    public static function success(string $redirectUrl): self
    {
        return new self(succeeded: true, redirectUrl: $redirectUrl);
    }

    public static function failure(string $errorField, string $errorMessage): self
    {
        return new self(succeeded: false, errorField: $errorField, errorMessage: $errorMessage);
    }
}
