<?php // database/seeders/DemoShopSeeder.php

namespace Database\Seeders;

use App\Domain\Discount\Models\DiscountCode;
use App\Domain\Shipping\Models\ShippingRate;
use App\Domain\Shipping\Models\ShippingZone;
use Illuminate\Database\Seeder;

/**
 * Shipping zones, rates and a discount code — infrastructure a checkout needs
 * regardless of what is in the catalogue.
 *
 * This used to also seed three synthetic products (Bifold wallet, Card
 * holder, Long wallet) to have something to check out with before real
 * photography existed. Real product photographs now come from
 * WalletImagesSeeder, and the three synthetic ones were still sitting in the
 * catalogue as unphotographed placeholder tiles — removed from the database
 * and from here, so re-running this seeder cannot bring them back.
 */
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

        DiscountCode::create([
            'code' => 'WELCOME10', 'kind' => 'percent', 'value' => 10,
            'minimum_order_minor' => 5000, 'usage_limit' => 100, 'times_used' => 0,
            'is_active' => true,
        ]);
    }
}
