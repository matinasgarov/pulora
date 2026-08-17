@php
    $headingClass = 'font-sans text-[10px] uppercase tracking-[0.16em] text-bark-muted';
    $linkClass = 'font-sans text-sm text-ground hover:text-accent-light';
    $plainClass = 'font-sans text-sm text-bark-muted';
@endphp

{{-- Dark, like the hero. The storefront was white and cream from top to
     bottom, which left it with nothing to sit against; anchoring both ends on
     bark gives the pale middle a frame instead of letting it run off the edge
     of the screen. Every colour here is a token measured against bark in
     ContrastTest — the light-ground greys all vanish on this surface. --}}
<footer class="mt-24 bg-bark px-4 pt-[70px] pb-10 sm:px-11">
    <div class="flex flex-col gap-10 md:grid md:grid-cols-[2fr_1fr_1fr_1fr] md:gap-12">
        <div>
            <a href="{{ route('storefront.catalogue', absolute: false) }}"
               class="font-display text-[19px] uppercase tracking-[0.40em] text-ground">Pulora</a>
            <p class="mt-3 font-sans text-sm text-bark-muted">{{ __('shop.footer.brand.tagline') }}</p>
            <p class="mt-4 font-sans text-sm leading-relaxed text-ground/85">
                {{ __('shop.footer.brand.address') }}<br>
                {{ __('shop.footer.brand.hours') }}
            </p>
            <p class="mt-4 font-sans text-sm leading-relaxed text-bark-muted">{{ __('shop.footer.brand.craft') }}</p>
        </div>

        <div class="flex flex-col gap-3">
            <p class="{{ $headingClass }}">{{ __('shop.footer.headings.shop') }}</p>
            <a href="{{ route('storefront.catalogue', absolute: false) }}" class="{{ $linkClass }}">{{ __('shop.footer.links.all_products') }}</a>
            {{-- Real destinations now that the catalogue filters on category.
                 These were plain text while that was unbuilt. --}}
            <a href="{{ route('storefront.catalogue', ['category' => 'wallet'], absolute: false) }}#shop" class="{{ $linkClass }}">{{ __('shop.footer.links.wallets') }}</a>
            <a href="{{ route('storefront.catalogue', ['category' => 'card'], absolute: false) }}#shop" class="{{ $linkClass }}">{{ __('shop.footer.links.card_holders') }}</a>
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

    <div class="mt-14 flex flex-col gap-2 border-t border-bark-muted/25 pt-6 font-sans text-[10px] uppercase tracking-[0.16em] text-bark-muted sm:flex-row sm:items-center sm:justify-between">
        <span>{{ __('shop.footer.legal.copyright') }}</span>
        <span>{{ __('shop.footer.legal.made_in') }}</span>
    </div>
</footer>
