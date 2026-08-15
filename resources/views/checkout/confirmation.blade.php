{{-- Static cart badge: see orders/lookup.blade.php. The cart is likely just
     empty here (cleared once the order is confirmed paid), but there is no
     on-page action that would need the badge to update live either way. --}}
<x-layouts.storefront :title="__('shop.confirmation.title')" :live-cart="false">
    <div class="mx-auto max-w-2xl px-6 py-24 text-center">
        <h1 class="font-serif text-3xl tracking-wide">{{ __('shop.confirmation.title') }}</h1>

        <p class="mt-6 font-sans text-sm leading-relaxed text-muted">
            {{ __('shop.confirmation.body', ['number' => session('last_order_number')]) }}
        </p>

        <a href="{{ route('storefront.catalogue', absolute: false) }}"
           class="mt-12 inline-block bg-ink px-6 py-4 text-center font-serif text-sm tracking-widest text-ground hover:bg-accent">
            {{ __('shop.nav.catalogue') }}
        </a>
    </div>
</x-layouts.storefront>
