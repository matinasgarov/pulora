{{-- resources/views/mail/order-confirmation.blade.php --}}
@component('mail::message')
# Thank you, {{ $order->customer_name }}

We have received your order **{{ $order->order_number }}** and started work on it.

@component('mail::table')
| Item | Qty | Price |
|:-----|:---:|------:|
@foreach ($order->items as $item)
| {{ $item->product_name }} — {{ $item->variant_description }}@if($item->personalization) ({{ implode(', ', $item->personalization) }})@endif | {{ $item->quantity }} | {{ \App\Domain\Money::format($item->line_total_minor) }} |
@endforeach
@endcomponent

Shipping: {{ \App\Domain\Money::format($order->shipping_minor) }}
@if ($order->discount_minor > 0)
Discount: −{{ \App\Domain\Money::format($order->discount_minor) }}
@endif
**Total: {{ \App\Domain\Money::format($order->total_minor) }}**

Track your order any time with your email address and order number.

Thanks,<br>{{ config('app.name') }}
@endcomponent
