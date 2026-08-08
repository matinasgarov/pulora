<?php // app/Domain/Shipping/Models/ShippingZone.php

namespace App\Domain\Shipping\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    protected $guarded = [];

    protected $casts = ['country_codes' => 'array', 'is_fallback' => 'boolean'];

    public function rates() { return $this->hasMany(ShippingRate::class); }
}
