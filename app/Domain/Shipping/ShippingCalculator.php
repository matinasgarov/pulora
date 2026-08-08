<?php // app/Domain/Shipping/ShippingCalculator.php

namespace App\Domain\Shipping;

use App\Domain\Shipping\Models\ShippingRate;
use App\Domain\Shipping\Models\ShippingZone;

class ShippingCalculator
{
    /** @return ShippingQuote[] */
    public function quotesFor(string $countryCode, int $weightGrams): array
    {
        $zone = $this->zoneFor($countryCode);
        if (! $zone) {
            return [];
        }

        return ShippingRate::where('shipping_zone_id', $zone->id)
            ->where('min_weight_grams', '<=', $weightGrams)
            ->where('max_weight_grams', '>=', $weightGrams)
            ->orderBy('price_minor')
            ->get()
            ->map(fn (ShippingRate $r) => new ShippingQuote($r->id, $r->name, $r->price_minor))
            ->all();
    }

    public function quoteById(int $rateId, string $countryCode, int $weightGrams): ShippingQuote
    {
        foreach ($this->quotesFor($countryCode, $weightGrams) as $quote) {
            if ($quote->rateId === $rateId) {
                return $quote;
            }
        }

        throw new NoShippingRateException(
            "Shipping rate {$rateId} is not available for {$countryCode} at {$weightGrams}g."
        );
    }

    private function zoneFor(string $countryCode): ?ShippingZone
    {
        $code = strtoupper($countryCode);

        foreach (ShippingZone::orderBy('is_fallback')->get() as $zone) {
            if (in_array($code, $zone->country_codes, true)) {
                return $zone;
            }
        }

        return ShippingZone::where('is_fallback', true)->first();
    }
}
