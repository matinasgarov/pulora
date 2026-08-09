<?php // database/seeders/DemoShopSeeder.php

namespace Database\Seeders;

use App\Domain\Catalog\Models\PersonalizationOption;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Discount\Models\DiscountCode;
use App\Domain\Shipping\Models\ShippingRate;
use App\Domain\Shipping\Models\ShippingZone;
use Illuminate\Database\Seeder;

class DemoShopSeeder extends Seeder
{
    public function run(): void
    {
        $az = ShippingZone::create(['name' => 'Azerbaijan', 'country_codes' => ['AZ'], 'is_fallback' => false]);
        $regional = ShippingZone::create([
            'name' => 'Regional', 'country_codes' => ['TR', 'GE', 'RU', 'KZ'], 'is_fallback' => false,
        ]);
        $world = ShippingZone::create(['name' => 'Rest of world', 'country_codes' => [], 'is_fallback' => true]);

        foreach ([[$az, 500, 900], [$regional, 2500, 3500], [$world, 4500, 6500]] as [$zone, $light, $heavy]) {
            ShippingRate::create(['shipping_zone_id' => $zone->id, 'name' => 'Standard',
                'min_weight_grams' => 0, 'max_weight_grams' => 500, 'price_minor' => $light]);
            ShippingRate::create(['shipping_zone_id' => $zone->id, 'name' => 'Standard',
                'min_weight_grams' => 501, 'max_weight_grams' => 3000, 'price_minor' => $heavy]);
        }

        $catalog = [
            ['Bifold wallet', 8900, ['Cognac', 'Black', 'Natural']],
            ['Card holder', 4900, ['Cognac', 'Olive', 'Black']],
            ['Long wallet', 12900, ['Black', 'Natural']],
        ];

        foreach ($catalog as [$name, $price, $colours]) {
            $product = Product::create([
                'name' => $name,
                'slug' => str($name)->slug()->toString(),
                'description' => 'Hand-cut, hand-stitched, and finished in our Baku workshop.',
                'story' => 'Made from full-grain vegetable-tanned leather that darkens with use.',
                'base_price_minor' => $price,
                'lead_time_days' => 5,
                'is_active' => true,
            ]);

            foreach ($colours as $i => $colour) {
                Variant::create([
                    'product_id' => $product->id,
                    'sku' => strtoupper(str($name)->slug('')->limit(6, '')) . '-' . strtoupper(substr($colour, 0, 3)),
                    'description' => $colour,
                    'stock_quantity' => 4 + $i,
                    'weight_grams' => 120,
                    'is_active' => true,
                ]);
            }

            PersonalizationOption::create([
                'product_id' => $product->id,
                'type' => 'monogram',
                'label' => 'Monogram',
                'price_delta_minor' => 1000,
                'max_characters' => 3,
                'allowed_pattern' => '/^[A-Z]+$/',
                'is_required' => false,
            ]);
        }

        DiscountCode::create([
            'code' => 'WELCOME10', 'kind' => 'percent', 'value' => 10,
            'minimum_order_minor' => 5000, 'usage_limit' => 100, 'times_used' => 0,
            'is_active' => true,
        ]);
    }
}
