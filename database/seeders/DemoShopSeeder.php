<?php // database/seeders/DemoShopSeeder.php

namespace Database\Seeders;

use App\Domain\Catalog\Models\PersonalizationOption;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Catalog\ProductCategory;
use App\Domain\Catalog\ProductTag;
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
            [
                'name' => ['en' => 'Bifold wallet', 'az' => 'Bifold No.3'],
                'leather' => ['en' => 'Vegetable-tanned · Natural', 'az' => 'Bitkisel aşılanmış · Natural'],
                'category' => ProductCategory::Wallet,
                'tag' => ProductTag::New,
                'price' => 8900,
                'colours' => ['Cognac', 'Black', 'Natural'],
                'specs' => [
                    'en' => [
                        ['label' => 'Size', 'value' => '11 × 9 cm'],
                        ['label' => 'Card slots', 'value' => '6'],
                        ['label' => 'Stitching', 'value' => 'Saddle stitch, waxed linen'],
                        ['label' => 'Lining', 'value' => 'Unlined'],
                        ['label' => 'Made to order', 'value' => '5 business days'],
                    ],
                    'az' => [
                        ['label' => 'Ölçü', 'value' => '11 × 9 sm'],
                        ['label' => 'Kart yuvası', 'value' => '6'],
                        ['label' => 'Tikiş', 'value' => 'Sarrac tikişi, mumlu keten'],
                        ['label' => 'Astar', 'value' => 'Astarsız'],
                        ['label' => 'Hazırlanma', 'value' => '5 iş günü'],
                    ],
                ],
            ],
            [
                'name' => ['en' => 'Card holder', 'az' => 'Kart qabı No.1'],
                'leather' => ['en' => 'Calfskin · Dark brown', 'az' => 'Buzov · Tünd qəhvəyi'],
                'category' => ProductCategory::Card,
                'tag' => ProductTag::LowStock,
                'price' => 4900,
                'colours' => ['Cognac', 'Olive', 'Black'],
                'specs' => [
                    'en' => [
                        ['label' => 'Size', 'value' => '9.5 × 6.5 cm'],
                        ['label' => 'Card slots', 'value' => '4'],
                        ['label' => 'Stitching', 'value' => 'Saddle stitch, waxed linen'],
                        ['label' => 'Lining', 'value' => 'Unlined'],
                        ['label' => 'Made to order', 'value' => '5 business days'],
                    ],
                    'az' => [
                        ['label' => 'Ölçü', 'value' => '9.5 × 6.5 sm'],
                        ['label' => 'Kart yuvası', 'value' => '4'],
                        ['label' => 'Tikiş', 'value' => 'Sarrac tikişi, mumlu keten'],
                        ['label' => 'Astar', 'value' => 'Astarsız'],
                        ['label' => 'Hazırlanma', 'value' => '5 iş günü'],
                    ],
                ],
            ],
            [
                'name' => ['en' => 'Long wallet', 'az' => 'Uzun cüzdan No.2'],
                'leather' => ['en' => 'Shell cordovan · Cognac', 'az' => 'Shell cordovan · Konyak'],
                'category' => ProductCategory::Wallet,
                'tag' => null,
                'price' => 12900,
                'colours' => ['Black', 'Natural'],
                'specs' => [
                    'en' => [
                        ['label' => 'Size', 'value' => '19 × 10 cm'],
                        ['label' => 'Card slots', 'value' => '8'],
                        ['label' => 'Stitching', 'value' => 'Saddle stitch, waxed linen'],
                        ['label' => 'Lining', 'value' => 'Full leather lining'],
                        ['label' => 'Made to order', 'value' => '5 business days'],
                    ],
                    'az' => [
                        ['label' => 'Ölçü', 'value' => '19 × 10 sm'],
                        ['label' => 'Kart yuvası', 'value' => '8'],
                        ['label' => 'Tikiş', 'value' => 'Sarrac tikişi, mumlu keten'],
                        ['label' => 'Astar', 'value' => 'Tam dəri astar'],
                        ['label' => 'Hazırlanma', 'value' => '5 iş günü'],
                    ],
                ],
            ],
        ];

        foreach ($catalog as $item) {
            $slug = str($item['name']['en'])->slug()->toString();

            $product = Product::create([
                'name' => $item['name'],
                'slug' => $slug,
                'description' => [
                    'en' => 'Hand-cut, hand-stitched, and finished in our Baku workshop.',
                    'az' => 'Bakı emalatxanamızda əl ilə kəsilir, tikilir və hazırlanır.',
                ],
                'story' => [
                    'en' => 'Made from full-grain vegetable-tanned leather that darkens with use.',
                    'az' => 'İstifadə ilə tündləşən tam dənəli, bitkisel aşılanmış dəridən hazırlanıb.',
                ],
                'leather' => $item['leather'],
                'category' => $item['category']->value,
                'tag' => $item['tag']?->value,
                'specs' => $item['specs'],
                'base_price_minor' => $item['price'],
                'lead_time_days' => 5,
                'is_active' => true,
            ]);

            $name = $item['name']['en'];
            $colours = $item['colours'];

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
