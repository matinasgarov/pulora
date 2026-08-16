@php
    $headingClass = 'font-sans text-[10px] uppercase tracking-[0.16em] text-muted';
    $linkClass = 'font-sans text-sm text-ink-soft hover:text-accent';
    $plainClass = 'font-sans text-sm text-muted-lighter';
@endphp

<footer class="mt-24 border-t border-rule px-4 pt-[70px] pb-10 sm:px-11">
    <div class="flex flex-col gap-10 md:grid md:grid-cols-[2fr_1fr_1fr_1fr] md:gap-12">
        <div>
            <a href="{{ route('storefront.catalogue', absolute: false) }}"
               class="font-display text-[19px] uppercase tracking-[0.40em] text-ink">Pulora</a>
            <p class="mt-3 font-sans text-sm text-muted">{{ __('shop.footer.brand.tagline') }}</p>
            <p class="mt-4 font-sans text-sm leading-relaxed text-ink-soft">
                {{ __('shop.footer.brand.address') }}<br>
                {{ __('shop.footer.brand.hours') }}
            </p>
            <p class="mt-4 font-sans text-sm leading-relaxed text-muted">{{ __('shop.footer.brand.craft') }}</p>
        </div>

        <div class="flex flex-col gap-3">
            <p class="{{ $headingClass }}">{{ __('shop.footer.headings.shop') }}</p>
            <a href="{{ route('storefront.catalogue', absolute: false) }}" class="{{ $linkClass }}">{{ __('shop.footer.links.all_products') }}</a>
            {{-- Category filtering is Phase 2 — these are not yet real
                 destinations, so they render as plain text rather than a link
                 that goes nowhere. --}}
            <span class="{{ $plainClass }}">{{ __('shop.footer.links.wallets') }}</span>
            <span class="{{ $plainClass }}">{{ __('shop.footer.links.card_holders') }}</span>
        </div>

        <div class="flex flex-col gap-3">
            <p class="{{ $headingClass }}">{{ __('shop.footer.headings.service') }}</p>
            <a href="{{ route('orders.lookup', absolute: false) }}" class="{{ $linkClass }}">{{ __('shop.orders.find_title') }}</a>
            <span class="{{ $plainClass }}">{{ __('shop.footer.links.shipping') }}</span>
            <span class="{{ $plainClass }}">{{ __('shop.footer.links.returns') }}</span>
        </div>

        <div class="flex flex-col gap-3">
            <p class="{{ $headingClass }}">{{ __('shop.footer.headings.contact') }}</p>
            {{-- The brand block on the left already carries the address and
                 opening hours; repeating them here read as a mistake rather
                 than as contact detail. This column is how to reach a person. --}}
            <a href="mailto:{{ __('shop.footer.contact.email') }}" class="{{ $linkClass }}">{{ __('shop.footer.contact.email') }}</a>
            <span class="{{ $plainClass }}">{{ __('shop.footer.contact.instagram') }}</span>
            <span class="{{ $plainClass }}">{{ __('shop.footer.contact.whatsapp') }}</span>
        </div>
    </div>

    <div class="mt-14 flex flex-col gap-2 border-t border-rule pt-6 font-sans text-[10px] uppercase tracking-[0.16em] text-muted-lighter sm:flex-row sm:items-center sm:justify-between">
        <span>{{ __('shop.footer.legal.copyright') }}</span>
        <span>{{ __('shop.footer.legal.made_in') }}</span>
    </div>
</footer>
