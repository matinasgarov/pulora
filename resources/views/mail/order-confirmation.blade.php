{{-- resources/views/mail/order-confirmation.blade.php --}}
@component('mail::message')
# {{ __('shop.mail.thank_you', ['name' => $order->customer_name]) }}

{{ __('shop.mail.received', ['number' => $order->order_number]) }}

@component('mail::table')
| {{ __('shop.mail.item') }} | {{ __('shop.mail.qty') }} | {{ __('shop.mail.price') }} |
|:-----|:---:|------:|
@foreach ($order->items as $item)
| {{ $item->product_name }} — {{ $item->variant_description }}@if($item->personalization) ({{ implode(', ', $item->personalization) }})@endif | {{ $item->quantity }} | {{ \App\Domain\Money::format($item->line_total_minor) }} |
@endforeach
@endcomponent

{{ __('shop.mail.shipping') }}: {{ \App\Domain\Money::format($order->shipping_minor) }}
@if ($order->discount_minor > 0)
{{ __('shop.mail.discount') }}: −{{ \App\Domain\Money::format($order->discount_minor) }}
@endif
**{{ __('shop.mail.total') }}: {{ \App\Domain\Money::format($order->total_minor) }}**

{{ __('shop.mail.track') }}

{{ __('shop.mail.thanks') }}<br>{{ config('app.name') }}
@endcomponent
