<?php // app/Domain/Payment/PaymentGateway.php

namespace App\Domain\Payment;

use App\Domain\Order\Models\Order;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function createPayment(Order $order): PaymentRedirect;

    public function verifyCallback(Request $request): CallbackResult;

    public function refund(Order $order, int $amountMinor): RefundResult;
}
