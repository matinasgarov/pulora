<?php // database/factories/OrderFactory.php

namespace Database\Factories;

use App\Domain\Order\Models\Order;
use App\Domain\Order\OrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'order_number' => 'LS-2026-' . Str::upper(Str::random(6)),
            'status' => OrderStatus::PendingPayment,
            'source' => 'web',
            'customer_email' => fake()->safeEmail(),
            'customer_name' => fake()->name(),
            'address_line1' => '1 Nizami St',
            'city' => 'Baku',
            'country_code' => 'AZ',
            'subtotal_minor' => 8900,
            'shipping_minor' => 500,
            'discount_minor' => 0,
            'total_minor' => 9400,
            'currency' => 'AZN',
            'total_weight_grams' => 120,
        ];
    }
}
