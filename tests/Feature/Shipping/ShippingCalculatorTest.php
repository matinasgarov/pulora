<?php // tests/Feature/Shipping/ShippingCalculatorTest.php

use App\Domain\Shipping\NoShippingRateException;
use App\Domain\Shipping\ShippingCalculator;
use App\Domain\Shipping\Models\ShippingRate;
use App\Domain\Shipping\Models\ShippingZone;

beforeEach(function () {
    $az = ShippingZone::create(['name' => 'Azerbaijan', 'country_codes' => ['AZ'], 'is_fallback' => false]);
    $world = ShippingZone::create(['name' => 'Rest of world', 'country_codes' => [], 'is_fallback' => true]);

    ShippingRate::create(['shipping_zone_id' => $az->id, 'name' => 'Standard',
        'min_weight_grams' => 0, 'max_weight_grams' => 500, 'price_minor' => 500]);
    ShippingRate::create(['shipping_zone_id' => $az->id, 'name' => 'Standard',
        'min_weight_grams' => 501, 'max_weight_grams' => 2000, 'price_minor' => 900]);
    ShippingRate::create(['shipping_zone_id' => $world->id, 'name' => 'International',
        'min_weight_grams' => 0, 'max_weight_grams' => 2000, 'price_minor' => 4500]);

    $this->calc = app(ShippingCalculator::class);
});

it('picks the rate whose weight bracket contains the order', function () {
    expect($this->calc->quotesFor('AZ', 300)[0]->priceMinor)->toBe(500);
    expect($this->calc->quotesFor('AZ', 800)[0]->priceMinor)->toBe(900);
});

it('treats bracket boundaries as inclusive on both ends', function () {
    expect($this->calc->quotesFor('AZ', 500)[0]->priceMinor)->toBe(500);
    expect($this->calc->quotesFor('AZ', 501)[0]->priceMinor)->toBe(900);
});

it('falls back to the catch-all zone for an unlisted country', function () {
    expect($this->calc->quotesFor('DE', 300)[0]->priceMinor)->toBe(4500);
});

it('returns no quotes when the parcel exceeds every bracket', function () {
    expect($this->calc->quotesFor('AZ', 9999))->toBe([]);
});

it('rejects a rate id that is not valid for the destination', function () {
    $azRateId = ShippingRate::where('price_minor', 500)->first()->id;

    $this->calc->quoteById($azRateId, 'DE', 300);
})->throws(NoShippingRateException::class);
