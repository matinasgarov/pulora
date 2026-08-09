<?php // app/Domain/Order/Models/Order.php

namespace App\Domain\Order\Models;

use App\Domain\Order\OrderStatus;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => OrderStatus::class,
        'subtotal_minor' => 'integer',
        'shipping_minor' => 'integer',
        'discount_minor' => 'integer',
        'total_minor' => 'integer',
        'total_weight_grams' => 'integer',
        'customs_value_minor' => 'integer',
        'reserved_until' => 'datetime',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
    ];

    public function items() { return $this->hasMany(OrderItem::class); }
}
