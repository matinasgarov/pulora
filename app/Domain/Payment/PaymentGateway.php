<?php // app/Domain/Payment/PaymentGateway.php

namespace App\Domain\Payment;

use App\Domain\Order\Models\Order;
use Illuminate\Http\Request;

interface PaymentGateway
{
    /**
     * The canonical name written to payment_logs.gateway. Every write site
     * (this gateway's own logging and OrderService's refund logging) must use
     * this instead of class_basename() or a hardcoded literal, so the same
     * gateway never shows up under two different names in the admin panel.
     */
    public function gatewayName(): string;

    public function createPayment(Order $order): PaymentRedirect;

    public function verifyCallback(Request $request): CallbackResult;

    public function refund(Order $order, int $amountMinor): RefundResult;
}
