<?php // app/Domain/Payment/Models/PaymentLog.php

namespace App\Domain\Payment\Models;

use App\Domain\Order\Models\Order;
use Illuminate\Database\Eloquent\Model;

class PaymentLog extends Model
{
    protected $guarded = [];

    protected $casts = ['payload' => 'array'];

    public function order() { return $this->belongsTo(Order::class); }
}
