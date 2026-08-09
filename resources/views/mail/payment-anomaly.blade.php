@component('mail::message')
# Payment anomaly

**{{ $reason }}**

Reference: `{{ $reference }}`
@if ($ip)
Source IP: `{{ $ip }}`
@endif

Check the `payment_logs` table for the full payload.
@endcomponent
