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

    {{-- Stretch rather than items-center: centring shrink-wraps the nav to
         max-content, which leaves flex-wrap below with no width to wrap
         against. Stretched children take the container width and centre their
         own contents, so the nav can actually wrap on a narrow screen. --}}
    <div class="flex flex-col gap-6 px-6 py-8 text-center">
        <a href="{{ route('storefront.catalogue', absolute: false) }}"
           class="font-serif text-2xl tracking-[0.2em] text-accent">
            Pulora
        </a>

        {{-- Wraps: at 390px the four items overflowed and pushed the locale
             switcher off the right edge, so a phone could not change language
             at all. --}}
        {{-- Four items with wide tracking are the tightest row on the site, and
             the Azerbaijani labels are the longer pair, so the locale switcher
             is what falls off a narrow screen first. Smaller type and tighter
             gaps below sm, and a nav that can wrap rather than overflow.

             NOTE: not verified at true phone widths. Headless Chrome on this
             machine clamps the window to ~484px, so a --window-size=390
             screenshot is a 390px crop of a 484px layout, not a 390px render.
             Worth a look on a real device or with CDP device emulation. --}}
        <nav class="flex w-full flex-wrap items-center justify-center gap-x-5 gap-y-3 font-serif text-xs tracking-wide sm:gap-x-8 sm:text-sm sm:tracking-widest">
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
