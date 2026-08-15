<x-mail::message>
# {{ __('shop.mail.shipped', ['number' => $order->order_number]) }}

{{ __('shop.mail.shipped_greeting', ['name' => $order->customer_name]) }}

{{ __('shop.mail.shipped_posted', ['number' => $order->order_number]) }}

{{ __('shop.mail.tracking_number', ['tracking' => $order->tracking_number]) }}

{{ __('shop.mail.shipped_thanks') }}

{{ __('shop.mail.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
