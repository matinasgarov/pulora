<?php // app/Domain/Order/Models/OrderEvent.php

namespace App\Domain\Order\Models;

use Illuminate\Database\Eloquent\Model;

class OrderEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime'];

    public function order() { return $this->belongsTo(Order::class); }
}
