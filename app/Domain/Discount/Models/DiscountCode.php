<?php // app/Domain/Discount/Models/DiscountCode.php

namespace App\Domain\Discount\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountCode extends Model
{
    protected $guarded = [];

    protected $casts = [
        'value' => 'integer',
        'minimum_order_minor' => 'integer',
        'usage_limit' => 'integer',
        'times_used' => 'integer',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];
}
