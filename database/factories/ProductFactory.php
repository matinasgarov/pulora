<?php // database/factories/ProductFactory.php

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\ProductCategory;
use App\Domain\Catalog\ProductTag;
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
            'leather' => ['en' => 'Vegetable-tanned · Natural', 'az' => 'Bitkisel aşılanmış · Natural'],
            'category' => fake()->randomElement(ProductCategory::cases())->value,
            'tag' => null,
            'specs' => [
                'en' => [
                    ['label' => 'Dimensions', 'value' => '11 × 9 cm'],
                    ['label' => 'Stitching', 'value' => 'Saddle stitch, waxed linen'],
                ],
                'az' => [
                    ['label' => 'Ölçü', 'value' => '11 × 9 sm'],
                    ['label' => 'Tikiş', 'value' => 'Sarrac tikişi, mumlu keten'],
                ],
            ],
            'base_price_minor' => fake()->numberBetween(5000, 20000),
            'lead_time_days' => 3,
            'is_active' => true,
        ];
    }

    public function withTag(ProductTag $tag): static
    {
        return $this->state(fn () => ['tag' => $tag->value]);
    }
}
