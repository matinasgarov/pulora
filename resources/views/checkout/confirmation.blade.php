{{-- Static cart badge: see orders/lookup.blade.php. The cart is likely just
     empty here (cleared once the order is confirmed paid), but there is no
     on-page action that would need the badge to update live either way. --}}
<x-layouts.storefront :title="__('shop.confirmation.title')" :live-cart="false">
    <div class="mx-auto max-w-2xl px-4 py-24 text-center sm:px-11">
        <h1 class="font-display text-3xl tracking-wide text-ink">{{ __('shop.confirmation.title') }}</h1>

        <p class="mt-6 font-sans text-sm leading-relaxed text-muted">
            {{ __('shop.confirmation.body', ['number' => session('last_order_number')]) }}
        </p>

        <a href="{{ route('storefront.catalogue', absolute: false) }}"
           class="mt-12 inline-block bg-ink px-6 py-[19px] text-center font-sans text-[11px] uppercase tracking-[0.18em] text-ground hover:bg-accent">
            {{ __('shop.nav.catalogue') }}
        </a>
    </div>
</x-layouts.storefront>
