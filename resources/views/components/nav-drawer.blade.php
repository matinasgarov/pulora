@php
    $otherLocale = app()->getLocale() === 'en' ? 'az' : 'en';
    $currentLocale = app()->getLocale();
@endphp

{{-- Rendered as a sibling of <header> in the layout, never nested inside it:
     the header's backdrop-filter creates a containing block that traps
     position: fixed descendants, so a drawer nested inside <header> would be
     clipped to the header's own box instead of covering the viewport. --}}
<input type="checkbox" id="nav-drawer-toggle" class="peer/drawer sr-only">

{{-- Scrim. A <label> rather than a script handler, so closing it needs no
     JavaScript either. --}}
<label for="nav-drawer-toggle"
       class="fixed inset-0 z-50 hidden cursor-pointer bg-[rgba(23,19,16,0.25)] peer-checked/drawer:block"
       aria-hidden="true"></label>

<aside class="fixed right-0 top-0 z-[60] flex h-full w-full min-[400px]:w-[360px] translate-x-full flex-col bg-ground px-10 py-[34px] transition-transform duration-300 ease-out peer-checked/drawer:translate-x-0"
       aria-label="{{ __('shop.nav.menu') }}">
    <div class="flex justify-end">
        <label for="nav-drawer-toggle"
               class="grid size-11 cursor-pointer place-items-center rounded-full text-ink transition-colors hover:bg-rule-light"
               aria-label="{{ __('shop.nav.close') }}">
            <svg viewBox="0 0 20 20" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" aria-hidden="true">
                <line x1="5" y1="5" x2="15" y2="15" />
                <line x1="15" y1="5" x2="5" y2="15" />
            </svg>
        </label>
    </div>

    <nav class="mt-11 flex flex-col gap-[22px] font-display text-[26px]">
        <a href="{{ route('storefront.catalogue', absolute: false) }}" class="hover:text-accent">{{ __('shop.nav.catalogue') }}</a>
        <a href="{{ route('orders.lookup', absolute: false) }}" class="hover:text-accent">{{ __('shop.nav.orders') }}</a>
        <a href="{{ route('storefront.catalogue', absolute: false) }}#atelier" class="hover:text-accent">{{ __('shop.nav.atelier') }}</a>
    </nav>

    {{-- Wishlist row is Phase 2 and intentionally omitted — a heart that does
         nothing would be worse than no heart at all. --}}

    <div class="mt-10 flex items-center justify-between border-t border-rule pt-6 font-sans text-[11px] uppercase tracking-[0.16em]">
        <span class="text-muted">{{ __('shop.nav.language') }}</span>
        <span class="flex items-center gap-3">
            <a href="/az" hreflang="az"
               class="{{ $currentLocale === 'az' ? 'text-ink underline underline-offset-4' : 'text-muted-lighter hover:text-accent' }}">AZ</a>
            <a href="/en" hreflang="en"
               class="{{ $currentLocale === 'en' ? 'text-ink underline underline-offset-4' : 'text-muted-lighter hover:text-accent' }}">EN</a>
        </span>
    </div>

    <div class="mt-auto border-t border-rule pt-6 font-sans text-[11px] leading-relaxed text-muted">
        <p>{{ __('shop.footer.brand.address') }}</p>
        <p>{{ __('shop.footer.brand.hours') }}</p>
        <p class="mt-2">
            <a href="mailto:{{ __('shop.footer.contact.email') }}" class="text-accent">{{ __('shop.footer.contact.email') }}</a>
        </p>
    </div>
</aside>
