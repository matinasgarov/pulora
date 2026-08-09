<?php // app/Domain/Order/Models/OrderItem.php

namespace App\Domain\Order\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'personalization' => 'array',
        'unit_price_minor' => 'integer',
        'quantity' => 'integer',
        'line_total_minor' => 'integer',
        'weight_grams' => 'integer',
    ];
}
