<!doctype html>
<title>Find your order</title>
<h1>Find your order</h1>

@if (! empty($notFound))
    <p>We could not find an order matching that email address and order number.</p>
@endif

<form method="POST" action="{{ route('orders.lookup.find') }}">
    @csrf
    <label>
        Email
        <input type="email" name="email" value="{{ old('email') }}" required>
    </label>
    <label>
        Order number
        <input type="text" name="order_number" value="{{ old('order_number') }}" required>
    </label>
    <button type="submit">Find my order</button>
</form>

@if ($errors->any())
    <ul>
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif
