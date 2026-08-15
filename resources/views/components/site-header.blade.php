@php
    $otherLocale = app()->getLocale() === 'en' ? 'az' : 'en';
@endphp

<header class="border-b border-ink/10">
    <div class="bg-ground py-2 text-center font-serif text-xs tracking-widest text-muted">
        {{ __('shop.announcement') }}
    </div>

    <div class="flex flex-col items-center gap-6 px-6 py-8">
        <a href="{{ route('storefront.catalogue', absolute: false) }}"
           class="font-serif text-2xl tracking-[0.2em] text-accent">
            Pulora
        </a>

        <nav class="flex items-center gap-10 font-serif text-sm tracking-widest">
            <a href="{{ route('storefront.catalogue', absolute: false) }}" class="hover:text-accent">{{ __('shop.nav.catalogue') }}</a>
            <a href="{{ route('orders.lookup') }}" class="hover:text-accent">{{ __('shop.nav.orders') }}</a>
            <a href="{{ route('storefront.cart') }}" class="hover:text-accent">
                {{ __('shop.nav.cart') }}
                {{-- Task 7 replaces this with <livewire:cart-count /> --}}
            </a>
            <a href="/{{ $otherLocale }}"
               hreflang="{{ $otherLocale }}"
               class="uppercase text-muted hover:text-accent">{{ $otherLocale }}</a>
        </nav>
    </div>
</header>
