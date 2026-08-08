<?php // app/Http/Controllers/PaymentCallbackController.php

namespace App\Http\Controllers;

use App\Domain\Order\Models\Order;
use App\Domain\Order\OrderService;
use App\Domain\Payment\PaymentGateway;
use App\Mail\PaymentAnomaly;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentCallbackController extends Controller
{
    public function __construct(
        private PaymentGateway $gateway,
        private OrderService $orders,
    ) {}

    public function __invoke(Request $request): Response
    {
        $result = $this->gateway->verifyCallback($request);

        if (! $result->isValid) {
            Log::warning('Rejected payment callback with invalid signature', [
                'reference' => $result->reference,
                'ip' => $request->ip(),
            ]);

            Mail::to(config('shop.operator_email'))->send(
                new PaymentAnomaly('Invalid callback signature', $result->reference, $request->ip())
            );

            return response('invalid signature', 400);
        }

        $order = Order::where('payment_reference', $result->reference)->first();

        if (! $order) {
            Log::warning('Payment callback for unknown reference', ['reference' => $result->reference]);

            Mail::to(config('shop.operator_email'))->send(
                new PaymentAnomaly('Callback for unknown reference', $result->reference, $request->ip())
            );

            return response('unknown reference', 404);
        }

        if ($result->isPaid) {
            $this->orders->markPaid($order, $result->reference);
        }

        return response('ok', 200);
    }
}
