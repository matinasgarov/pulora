<?php // app/Domain/Payment/Models/PaymentLog.php

namespace App\Domain\Payment\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentLog extends Model
{
    protected $guarded = [];

    protected $casts = ['payload' => 'array'];
}
