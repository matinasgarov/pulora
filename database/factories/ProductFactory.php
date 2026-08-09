<?php // database/factories/ProductFactory.php

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true) . ' wallet';

        return [
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'description' => fake()->sentence(),
            'base_price_minor' => fake()->numberBetween(5000, 20000),
            'lead_time_days' => 3,
            'is_active' => true,
        ];
    }
}
