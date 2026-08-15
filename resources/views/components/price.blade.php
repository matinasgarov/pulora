@props(['minor'])

{{-- The one place the storefront turns minor units into text. --}}
<span {{ $attributes->merge(['class' => 'tabular-nums']) }}>{{ \App\Domain\Money::format($minor) }}</span>
