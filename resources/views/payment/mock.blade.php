<!doctype html>
<title>Mock gateway</title>
<h1>Mock payment gateway</h1>
<p>Reference: <strong>{{ $reference }}</strong></p>
<form method="POST" action="{{ route('payment.callback') }}">
    @csrf
    <input type="hidden" name="reference" value="{{ $reference }}">
    <input type="hidden" name="status" value="paid">
    <input type="hidden" name="amount_minor" value="{{ $amountMinor }}">
    <input type="hidden" name="currency" value="{{ $currency }}">
    <input type="hidden" name="signature"
           value="{{ hash_hmac('sha256', $reference . '|paid|' . $amountMinor . '|' . $currency, config('services.payment.mock_secret')) }}">
    <button type="submit">Pay now</button>
</form>
