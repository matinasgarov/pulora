<!doctype html>
<title>Order {{ $order->order_number }}</title>
<h1>Order {{ $order->order_number }}</h1>
<p>Status: {{ $order->status->label() }}</p>

@if ($order->tracking_number)
    <p>Tracking number: {{ $order->tracking_number }}</p>
@endif

<table>
    <thead>
        <tr>
            <th>Item</th>
            <th>Qty</th>
            <th>Price</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($order->items as $item)
            <tr>
                <td>
                    {{ $item->product_name }} — {{ $item->variant_description }}
                    @if ($item->personalization)
                        ({{ implode(', ', $item->personalization) }})
                    @endif
                </td>
                <td>{{ $item->quantity }}</td>
                <td>{{ \App\Domain\Money::format($item->line_total_minor, $order->currency) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<p>Subtotal: {{ \App\Domain\Money::format($order->subtotal_minor, $order->currency) }}</p>
<p>Shipping: {{ \App\Domain\Money::format($order->shipping_minor, $order->currency) }}</p>
@if ($order->discount_minor > 0)
    <p>Discount: −{{ \App\Domain\Money::format($order->discount_minor, $order->currency) }}</p>
@endif
<p><strong>Total: {{ \App\Domain\Money::format($order->total_minor, $order->currency) }}</strong></p>
