<?php // database/factories/OrderItemFactory.php

namespace Database\Factories;

use App\Domain\Order\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'product_name' => 'Bifold wallet',
            'variant_description' => 'Cognac / natural thread',
            'sku' => strtoupper(fake()->unique()->bothify('WAL-####-??')),
            'unit_price_minor' => 4450,
            'quantity' => 1,
            'line_total_minor' => 4450,
            'personalization' => null,
            'weight_grams' => 120,
        ];
    }
}
