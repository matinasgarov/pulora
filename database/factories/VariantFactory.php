<?php // database/factories/VariantFactory.php

namespace Database\Factories;

use App\Domain\Catalog\Models\Variant;
use Illuminate\Database\Eloquent\Factories\Factory;

class VariantFactory extends Factory
{
    protected $model = Variant::class;

    public function definition(): array
    {
        return [
            'sku' => strtoupper(fake()->unique()->bothify('WAL-####-??')),
            'description' => 'Brown / natural thread',
            'price_minor_override' => null,
            'stock_quantity' => 10,
            'weight_grams' => 120,
            'is_active' => true,
        ];
    }
}
