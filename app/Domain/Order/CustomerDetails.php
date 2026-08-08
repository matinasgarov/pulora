<?php // app/Domain/Order/CustomerDetails.php

namespace App\Domain\Order;

final readonly class CustomerDetails
{
    public function __construct(
        public string $email,
        public string $name,
        public string $addressLine1,
        public ?string $addressLine2,
        public string $city,
        public ?string $postcode,
        public string $countryCode,
        public ?string $phone = null,
    ) {}
}
