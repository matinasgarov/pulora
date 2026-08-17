<x-layouts.storefront>
    {{-- Hero — 84vh, min 580px, content bottom-left. The backdrop is whatever
         has been dropped into public/media (see App\Support\HeroMedia); with
         nothing there it stays the placeholder frame naming the shot owed. --}}
    <section class="relative h-[84vh] min-h-[580px] overflow-hidden">
        {{-- Wrapped rather than passed `absolute inset-0` straight to the
             component: the component's own root is already `relative` (so
             the caption can anchor to it), and a `position` utility on the
             same element as another `position` utility is a same-specificity
             conflict Tailwind resolves by generated CSS order, not class
             order in markup — `relative` was winning and the frame was
             collapsing to the caption's own size instead of filling the
             hero. Sizing the wrapper and letting the component fill it with
             `h-full w-full` sidesteps the conflict entirely. --}}
        <div class="absolute inset-0">
            <x-hero-media :poster="$heroPoster" :video-sources="$heroVideoSources" />
        </div>

        <div class="absolute inset-x-0 bottom-0 px-4 pb-10 sm:px-11 sm:pb-[60px]">
            <h1 class="max-w-[600px] font-display text-[40px] leading-[1.04] tracking-[-0.01em] text-ink sm:text-[64px]">
                {{ __('shop.hero.line1') }}<br>
                {{ __('shop.hero.line2') }}<br>
                {{ __('shop.hero.line3') }}
            </h1>
            <p class="mt-6 max-w-[400px] font-sans text-[15px] leading-relaxed text-ink-soft">
                {{ __('shop.hero.body') }}
            </p>
            {{-- The bespoke destination doesn't exist until Phase 3 builds
                 the configurator, so this points at the catalogue below
                 (now the Collection section of this same page) instead of a
                 dead link. --}}
            <a href="#shop" class="mt-8 inline-block border-b border-ink pb-0.5 font-sans text-[13px] uppercase tracking-[0.14em] text-ink hover:border-accent hover:text-accent">
                {{ __('shop.hero.cta') }}
            </a>
        </div>
    </section>

    {{-- Collection — the former bare catalogue grid, now a section of the
         homepage. --}}
    <section id="shop" class="px-4 pt-[110px] sm:px-11">
        <div class="flex flex-wrap items-end justify-between gap-x-8 gap-y-4">
            <h2 class="font-display text-[13px] uppercase tracking-[0.28em] text-ink">
                {{ __('shop.collection.title') }}
            </h2>

            {{-- Category tabs are Phase 2 (search/filters/sort) and inert
                 here — rendered disabled rather than as controls that
                 silently do nothing. The active tab keeps a transparent
                 underline reserved on the inactive ones so the row never
                 shifts once tabs become live. --}}
            <div class="flex items-center gap-6 font-sans text-[11px] uppercase tracking-[0.16em]">
                <button type="button" disabled aria-current="true"
                        class="cursor-not-allowed border-b border-ink pb-1 text-ink opacity-60">
                    {{ __('shop.collection.tabs.all') }}
                </button>
                <button type="button" disabled
                        class="cursor-not-allowed border-b border-transparent pb-1 text-muted opacity-60">
                    {{ __('shop.collection.tabs.wallet') }}
                </button>
                <button type="button" disabled
                        class="cursor-not-allowed border-b border-transparent pb-1 text-muted opacity-60">
                    {{ __('shop.collection.tabs.card') }}
                </button>
            </div>
        </div>

        {{-- Toolbar: filter button and sort are also Phase 2 and inert; the
             count is real. --}}
        <div class="mt-6 flex flex-wrap items-center justify-between gap-4 border-y border-rule py-4 font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
            <button type="button" disabled
                    class="flex cursor-not-allowed items-center gap-2 opacity-60">
                <svg viewBox="0 0 20 20" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" aria-hidden="true">
                    <line x1="3" y1="6" x2="17" y2="6" />
                    <line x1="3" y1="10" x2="17" y2="10" />
                    <line x1="3" y1="14" x2="17" y2="14" />
                </svg>
                <span>{{ __('shop.collection.filters') }}</span>
                <span>({{ __('shop.collection.count', ['count' => $products->count()]) }})</span>
            </button>

            <label class="flex cursor-not-allowed items-center gap-3 opacity-60">
                <span>{{ __('shop.collection.sort') }}</span>
                <select disabled class="cursor-not-allowed border-b border-current bg-transparent py-0.5 pr-1 font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                    <option>{{ __('shop.collection.sort_options.featured') }}</option>
                    <option>{{ __('shop.collection.sort_options.price_asc') }}</option>
                    <option>{{ __('shop.collection.sort_options.price_desc') }}</option>
                    <option>{{ __('shop.collection.sort_options.newest') }}</option>
                </select>
            </label>
        </div>

        @if ($products->isEmpty())
            <div class="px-6 py-32 text-center font-display text-lg tracking-wide text-muted">
                {{ __('shop.catalogue.empty') }}
            </div>
        @else
            <div class="mt-10 grid grid-cols-1 gap-x-10 gap-y-[72px] [@media(min-width:560px)]:grid-cols-2 [@media(min-width:900px)]:grid-cols-3">
                @foreach ($products as $product)
                    <x-product-tile :product="$product" />
                @endforeach
            </div>
        @endif
    </section>

    {{-- Bespoke feature. --}}
    <section class="mt-[120px] border-t border-rule">
        <div class="grid grid-cols-1 lg:grid-cols-2">
            <x-placeholder-frame :caption="__('shop.placeholder.bespoke')" class="min-h-[620px]" />

            <div class="flex flex-col justify-center px-4 py-16 sm:px-11 lg:px-20 lg:py-[110px]">
                <p class="font-sans text-[11px] uppercase tracking-[0.2em] text-accent">
                    {{ __('shop.bespoke.eyebrow') }}
                </p>
                <h2 class="mt-4 font-display text-[32px] leading-tight text-ink sm:text-[44px]">
                    {{ __('shop.bespoke.heading') }}
                </h2>
                <p class="mt-6 max-w-[440px] font-sans text-[15px] leading-relaxed text-ink-soft">
                    {{ __('shop.bespoke.body') }}
                </p>

                <div class="mt-10 grid grid-cols-2 gap-x-8 gap-y-6">
                    <div class="border-t border-rule pt-4">
                        <p class="font-sans text-[10px] uppercase tracking-[0.16em] text-muted">
                            {{ __('shop.bespoke.facts.duration_label') }}
                        </p>
                        <p class="mt-2 font-display text-lg text-ink">
                            {{ __('shop.bespoke.facts.duration_value') }}
                        </p>
                    </div>
                    <div class="border-t border-rule pt-4">
                        <p class="font-sans text-[10px] uppercase tracking-[0.16em] text-muted">
                            {{ __('shop.bespoke.facts.starting_price_label') }}
                        </p>
                        <p class="mt-2 font-display text-lg text-ink">
                            <x-price :minor="$bespokeStartingPriceMinor" />
                        </p>
                    </div>
                </div>

                <a href="{{ $bespokeCtaHref }}"
                   class="mt-10 inline-block bg-ink px-[46px] py-[17px] text-center font-sans text-[11px] uppercase tracking-[0.18em] text-ground hover:bg-accent">
                    {{ __('shop.bespoke.cta') }}
                </a>
            </div>
        </div>
    </section>

    {{-- Atelier — centred pull quote. The link has no destination yet, so it
         renders as plain text rather than a dead anchor. --}}
    <section id="atelier" class="mx-auto mt-0 max-w-[680px] border-t border-rule px-4 py-[130px] text-center sm:px-11">
        <p class="font-sans text-[11px] uppercase tracking-[0.2em] text-accent">
            {{ __('shop.atelier.eyebrow') }}
        </p>
        <p class="mt-6 font-display text-[24px] leading-snug text-ink sm:text-[30px]">
            {{ __('shop.atelier.quote') }}
        </p>
        <span class="mt-8 inline-block border-b border-muted-lighter font-sans text-[13px] uppercase tracking-[0.14em] text-muted">
            {{ __('shop.atelier.link') }}
        </span>
    </section>
</x-layouts.storefront>
