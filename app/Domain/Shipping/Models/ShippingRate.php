<?php // app/Domain/Shipping/Models/ShippingRate.php

namespace App\Domain\Shipping\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingRate extends Model
{
    protected $guarded = [];

    protected $casts = [
        'min_weight_grams' => 'integer',
        'max_weight_grams' => 'integer',
        'price_minor' => 'integer',
    ];

    public function zone() { return $this->belongsTo(ShippingZone::class, 'shipping_zone_id'); }
}
