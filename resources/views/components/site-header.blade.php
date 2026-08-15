@props(['liveCart' => true])

@php
    $otherLocale = app()->getLocale() === 'en' ? 'az' : 'en';
@endphp

<header class="border-b border-ink/10">
    {{-- bg-tile, not bg-ground: on the ground colour this is indistinguishable
         from the page and reads as a stray line of text rather than a bar. --}}
    <div class="bg-tile py-2 text-center font-serif text-xs tracking-widest text-muted">
        {{ __('shop.announcement') }}
    </div>

    <div class="flex flex-col items-center gap-6 px-6 py-8">
        <a href="{{ route('storefront.catalogue', absolute: false) }}"
           class="font-serif text-2xl tracking-[0.2em] text-accent">
            Pulora
        </a>

        <nav class="flex items-center gap-10 font-serif text-sm tracking-widest">
            <a href="{{ route('storefront.catalogue', absolute: false) }}" class="hover:text-accent">{{ __('shop.nav.catalogue') }}</a>
            <a href="{{ route('orders.lookup', absolute: false) }}" class="hover:text-accent">{{ __('shop.nav.orders') }}</a>
            <a href="{{ route('storefront.cart', absolute: false) }}" class="hover:text-accent">
                {{ __('shop.nav.cart') }}
                {{-- A Livewire component embeds a per-render id and snapshot, so
                     two responses for the same state are never byte-identical.
                     OrderLookupTest requires exactly that of the lookup pages:
                     a wrong email and an unknown order number must be
                     indistinguishable, or the form becomes an oracle for
                     enumerating order numbers. Those pages therefore render the
                     count statically. Nothing on them mutates the cart, so
                     nothing is lost by not being live. --}}
                @if ($liveCart)
                    <livewire:cart-count />
                @else
                    <span>({{ app(\App\Domain\Cart\CartService::class)->snapshot()->totalQuantity() }})</span>
                @endif
            </a>
            <a href="/{{ $otherLocale }}"
               hreflang="{{ $otherLocale }}"
               class="uppercase text-muted hover:text-accent">{{ $otherLocale }}</a>
        </nav>
    </div>
</header>
