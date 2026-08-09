<x-mail::message>
# Your order is on its way

Hello {{ $order->customer_name }},

Order **{{ $order->order_number }}** has been posted.

Tracking number: **{{ $order->tracking_number }}**

Thank you for buying something made by hand.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
