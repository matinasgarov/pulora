<?php // app/Domain/Payment/MockGateway.php

namespace App\Domain\Payment;

use App\Domain\Order\Models\Order;
use App\Domain\Payment\Models\PaymentLog;
use Illuminate\Http\Request;

class MockGateway implements PaymentGateway
{
    public function __construct(private string $secret) {}

    public function createPayment(Order $order): PaymentRedirect
    {
        $reference = 'MOCK-' . $order->order_number;

        PaymentLog::create([
            'order_id' => $order->id,
            'gateway' => 'mock',
            'direction' => 'request',
            'reference' => $reference,
            'payload' => ['amount_minor' => $order->total_minor, 'currency' => $order->currency],
        ]);

        return new PaymentRedirect(
            url: route('payment.mock.form', ['reference' => $reference]),
            reference: $reference,
        );
    }

    public function verifyCallback(Request $request): CallbackResult
    {
        $reference = (string) $request->input('reference', '');
        $status = (string) $request->input('status', '');

        $order = Order::where('payment_reference', $reference)->first();

        $amountMinor = $request->has('amount_minor')
            ? (int) $request->input('amount_minor')
            : (int) ($order->total_minor ?? 0);
        $currency = $request->has('currency')
            ? (string) $request->input('currency')
            : (string) ($order->currency ?? 'AZN');

        $expected = hash_hmac('sha256', "{$reference}|{$status}|{$amountMinor}|{$currency}", $this->secret);
        $isValid = hash_equals($expected, (string) $request->input('signature', ''));

        PaymentLog::create([
            'order_id' => $order?->id,
            'gateway' => 'mock',
            'direction' => 'callback',
            'reference' => $reference,
            'payload' => ['valid' => $isValid] + $request->all(),
        ]);

        return new CallbackResult(
            isValid: $isValid,
            reference: $reference,
            isPaid: $isValid && $status === 'paid',
            amountMinor: $amountMinor,
            currency: $currency,
            raw: $request->all(),
        );
    }

    public function refund(Order $order, int $amountMinor): RefundResult
    {
        PaymentLog::create([
            'order_id' => $order->id,
            'gateway' => 'mock',
            'direction' => 'request',
            'reference' => $order->payment_reference,
            'payload' => ['refund_minor' => $amountMinor],
        ]);

        return new RefundResult(succeeded: true, reference: (string) $order->payment_reference);
    }
}
