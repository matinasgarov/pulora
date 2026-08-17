@props(['liveCart' => true])

@php
    $otherLocale = app()->getLocale() === 'en' ? 'az' : 'en';
    $iconBtn = 'grid size-11 sm:size-[38px] place-items-center rounded-full text-ink transition-colors hover:bg-rule-light';
@endphp

{{-- Sticky, translucent, blurred. z-20 sits below the drawer/scrim (z-50/60)
     so the drawer can cover it, and above ordinary page content. --}}
<header class="sticky top-0 z-20 border-b border-rule bg-[rgba(250,249,246,0.94)] backdrop-blur-[8px]">
    {{-- Declared as a direct child of <header> — a sibling of both the icon
         row (where its label lives) and the search panel below (which reacts
         to it via peer-checked). CSS's general sibling combinator only
         reaches elements sharing a parent, so nesting the checkbox inside the
         icon row would leave the panel, a sibling of the icon row rather than
         of the checkbox, unreachable from it. --}}
    <input type="checkbox" id="search-panel-toggle" class="peer/search sr-only">

    <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-4 px-4 py-4 sm:px-11 sm:py-[26px]">
        {{-- Text nav lives here on sm+ and is duplicated inside the drawer for
             mobile, where this column is hidden. The links themselves are
             plain anchors either way, so they work with JS entirely off. --}}
        <nav class="hidden items-center gap-[34px] font-sans text-[11px] uppercase tracking-[0.16em] sm:flex">
            <a href="{{ route('storefront.catalogue', absolute: false) }}" class="hover:text-accent">{{ __('shop.nav.catalogue') }}</a>
            <a href="{{ route('orders.lookup', absolute: false) }}" class="hover:text-accent">{{ __('shop.nav.orders') }}</a>
            <a href="{{ route('storefront.catalogue', absolute: false) }}#atelier" class="hover:text-accent">{{ __('shop.nav.atelier') }}</a>
        </nav>

        {{-- The trailing letter-spacing of a wide-tracked, centred inline
             element pushes its optical centre left (the last letter's
             trailing space counts toward width, but nothing balances it on
             the left). A matching text-indent adds that same space back on
             the left side, so the word sits back on-centre. --}}
        <a href="{{ route('storefront.catalogue', absolute: false) }}"
           class="col-start-2 justify-self-center font-display text-[26px] leading-none uppercase tracking-[0.42em] text-ink sm:text-[32px] [text-indent:0.42em]">
            Pulora
        </a>

        <div class="col-start-3 flex items-center justify-end gap-[14px]">
            {{-- Search toggle is entirely self-contained in the header: the
                 checkbox above, the label below acting as the icon button,
                 and the panel it reveals. The panel is normal in-flow content
                 (never position: fixed), so unlike the menu drawer it does
                 not need to escape the header's backdrop-filter containing
                 block. --}}
            <label for="search-panel-toggle"
                   class="{{ $iconBtn }} hidden cursor-pointer sm:grid"
                   aria-label="{{ __('shop.nav.search') }}">
                <svg viewBox="0 0 20 20" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" aria-hidden="true">
                    <circle cx="9" cy="9" r="6" />
                    <line x1="13.5" y1="13.5" x2="18" y2="18" />
                </svg>
            </label>

            <a href="{{ route('storefront.cart', absolute: false) }}"
               class="{{ $iconBtn }} relative"
               aria-label="{{ __('shop.nav.cart') }}">
                <svg viewBox="0 0 20 20" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4.5 7h11l-1 10.5h-9L4.5 7z" />
                    <path d="M7.25 7V5.5a2.75 2.75 0 0 1 5.5 0V7" />
                </svg>
                {{-- Real cart count, not a local counter: see the note by
                     $liveCart below for why the two branches exist. --}}
                @if ($liveCart)
                    <livewire:cart-count />
                @else
                    @php($staticCount = app(\App\Domain\Cart\CartService::class)->snapshot()->totalQuantity())
                    @if ($staticCount > 0)
                        <span class="absolute right-px top-[3px] grid min-w-[15px] h-[15px] place-items-center rounded-full bg-ink px-[3px] font-sans text-[9px] leading-none text-ground">{{ $staticCount }}</span>
                    @endif
                @endif
            </a>

            {{-- The checkbox and its label/scrim/panel siblings live outside
                 <header> — see <x-nav-drawer/> in the layout — because this
                 header's backdrop-filter creates a containing block that
                 traps position: fixed descendants. A drawer nested inside it
                 would be clipped to the header's own box instead of covering
                 the viewport. Only the trigger label lives here; label[for]
                 works across the DOM regardless of where the checkbox is. --}}
            <label for="nav-drawer-toggle"
                   class="{{ $iconBtn }} cursor-pointer"
                   aria-label="{{ __('shop.nav.menu') }}">
                <svg viewBox="0 0 20 20" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" aria-hidden="true">
                    <line x1="3" y1="6" x2="17" y2="6" />
                    <line x1="3" y1="10" x2="17" y2="10" />
                    <line x1="3" y1="14" x2="17" y2="14" />
                </svg>
            </label>
        </div>
    </div>

    {{-- Search is a plain GET form pointing at the catalogue, so pressing
         Enter works with scripting off and the result is a linkable URL. The
         #shop fragment lands on the grid rather than at the top of the hero,
         which is where the answer to a search actually is. --}}
    <div class="hidden border-t border-rule px-4 py-5 sm:px-11 sm:py-[22px] sm:pb-6 peer-checked/search:block">
        <form method="GET" action="{{ route('storefront.catalogue', absolute: false) }}"
              class="flex items-center gap-4 border-b border-rule pb-3">
            <svg viewBox="0 0 20 20" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" class="shrink-0 text-muted" aria-hidden="true">
                <circle cx="9" cy="9" r="6" />
                <line x1="13.5" y1="13.5" x2="18" y2="18" />
            </svg>
            <input type="search" name="q" value="{{ request('q') }}"
                   placeholder="{{ __('shop.search.placeholder') }}"
                   aria-label="{{ __('shop.nav.search') }}"
                   class="min-w-0 flex-1 border-0 bg-transparent font-sans text-[15px] font-light text-ink placeholder:text-muted-lightest focus:outline-none">
            <button type="submit"
                    class="shrink-0 font-sans text-[11px] uppercase tracking-[0.16em] text-ink hover:text-accent">
                {{ __('shop.nav.search') }}
            </button>
            <label for="search-panel-toggle"
                   class="shrink-0 cursor-pointer font-sans text-[11px] uppercase tracking-[0.16em] text-muted hover:text-accent">
                {{ __('shop.nav.close') }}
            </label>
        </form>
    </div>
</header>
