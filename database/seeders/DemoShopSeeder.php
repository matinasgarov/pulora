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
 *
 * Every write here is firstOrCreate, keyed on what actually identifies the
 * row. This runs on every container boot against a database that lives on a
 * volume and survives the redeploy, so the second run always finds the first
 * run's rows. With create() the discount code threw on its unique index and
 * took the whole boot down, and the zones and rates — which have no unique
 * index to stop them — were quietly duplicated on the way to that throw.
 *
 * The second argument is deliberately empty for zones and rates: everything
 * that identifies them is in the key, so there is nothing left to update, and
 * a value the operator has since corrected is never pushed back over.
 */
class DemoShopSeeder extends Seeder
{
    public function run(): void
    {
        $az = ShippingZone::firstOrCreate(
            ['name' => 'Azerbaijan'],
            ['country_codes' => ['AZ'], 'is_fallback' => false],
        );

        $regional = ShippingZone::firstOrCreate(
            ['name' => 'Regional'],
            ['country_codes' => ['TR', 'GE', 'RU', 'KZ'], 'is_fallback' => false],
        );

        $world = ShippingZone::firstOrCreate(
            ['name' => 'Rest of world'],
            ['country_codes' => [], 'is_fallback' => true],
        );

        foreach ([[$az, 500, 900], [$regional, 2500, 3500], [$world, 4500, 6500]] as [$zone, $light, $heavy]) {
            foreach ([[0, 500, $light], [501, 3000, $heavy]] as [$min, $max, $price]) {
                // The weight band is what makes a rate distinct within a zone;
                // two rows covering the same band would make the quote depend
                // on which one the query happened to return first.
                ShippingRate::firstOrCreate(
                    [
                        'shipping_zone_id' => $zone->id,
                        'name' => 'Standard',
                        'min_weight_grams' => $min,
                        'max_weight_grams' => $max,
                    ],
                    ['price_minor' => $price],
                );
            }
        }

        DiscountCode::firstOrCreate(
            ['code' => 'WELCOME10'],
            [
                'kind' => 'percent', 'value' => 10,
                'minimum_order_minor' => 5000, 'usage_limit' => 100, 'times_used' => 0,
                'is_active' => true,
            ],
        );
    }
}
