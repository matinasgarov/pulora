<?php // app/Http/Controllers/PaymentCallbackController.php

namespace App\Http\Controllers;

use App\Domain\Order\MarkPaidOutcome;
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

            Mail::to(config('shop.operator_email'))->queue(
                new PaymentAnomaly('Invalid callback signature', $result->reference, $request->ip())
            );

            return response('invalid signature', 400);
        }

        $order = Order::where('payment_reference', $result->reference)->first();

        if (! $order) {
            Log::warning('Payment callback for unknown reference', ['reference' => $result->reference]);

            Mail::to(config('shop.operator_email'))->queue(
                new PaymentAnomaly('Callback for unknown reference', $result->reference, $request->ip())
            );

            return response('unknown reference', 404);
        }

        if ($result->isPaid) {
            $outcome = $this->orders->markPaid(
                $order,
                $result->reference,
                $result->amountMinor,
                $result->currency,
            );

            if ($outcome === MarkPaidOutcome::NotPayable) {
                Log::warning('Payment received for an order that is no longer payable', [
                    'reference' => $result->reference,
                    'order_number' => $order->order_number,
                    'ip' => $request->ip(),
                ]);

                Mail::to(config('shop.operator_email'))->queue(new PaymentAnomaly(
                    'Payment received for an order that is no longer payable — refund required',
                    $order->order_number,
                    $request->ip(),
                ));
            }

            if ($outcome === MarkPaidOutcome::AmountMismatch) {
                Log::warning('Payment callback amount does not match order total', [
                    'reference' => $result->reference,
                    'order_number' => $order->order_number,
                    'paid_amount_minor' => $result->amountMinor,
                    'paid_currency' => $result->currency,
                    'ip' => $request->ip(),
                ]);

                Mail::to(config('shop.operator_email'))->queue(new PaymentAnomaly(
                    'Payment amount does not match order total — refund required',
                    $order->order_number,
                    $request->ip(),
                ));
            }
        }

        return response('ok', 200);
    }
}
