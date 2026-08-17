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

        {{-- The copy flips with the backdrop. On the photograph it sits on the
             dark scrim and goes light; on the placeholder frame there is no
             scrim and the ground is cream, so it stays ink. Hard-coding either
             one would make the other unreadable. --}}
        @php($onPhotograph = $heroPoster !== null)

        <div class="absolute inset-x-0 bottom-0 px-4 pb-10 sm:px-11 sm:pb-[60px]">
            <h1 @class([
                    'max-w-[600px] font-display text-[40px] leading-[1.04] tracking-[-0.01em] sm:text-[64px]',
                    'text-ground' => $onPhotograph,
                    'text-ink' => ! $onPhotograph,
                ])>
                {{ __('shop.hero.line1') }}<br>
                {{ __('shop.hero.line2') }}<br>
                {{ __('shop.hero.line3') }}
            </h1>
            <p @class([
                   'mt-6 max-w-[400px] font-sans text-[15px] leading-relaxed',
                   'text-bark-muted' => $onPhotograph,
                   'text-ink-soft' => ! $onPhotograph,
               ])>
                {{ __('shop.hero.body') }}
            </p>
            {{-- The bespoke destination doesn't exist until Phase 3 builds
                 the configurator, so this points at the catalogue below
                 (now the Collection section of this same page) instead of a
                 dead link. --}}
            <a href="#shop" @class([
                   'mt-8 inline-block border-b pb-0.5 font-sans text-[13px] uppercase tracking-[0.14em]',
                   'border-ground text-ground hover:border-accent-light hover:text-accent-light' => $onPhotograph,
                   'border-ink text-ink hover:border-accent hover:text-accent' => ! $onPhotograph,
               ])>
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

            {{-- Category tabs are links, not buttons: they change what the
                 page shows, which is a navigation, and a link keeps that
                 shareable, bookmarkable and reachable with the back button.
                 Each carries the rest of the state along, so choosing a
                 category does not silently discard a search. --}}
            <div class="flex items-center gap-6 font-sans text-[11px] uppercase tracking-[0.16em]">
                @foreach (['' => 'all', 'wallet' => 'wallet', 'card' => 'card'] as $value => $key)
                    @php($isCurrent = ($filter->category ?? '') === $value)
                    <a href="{{ route('storefront.catalogue', $filter->toQuery(['category' => $value]), absolute: false) }}#shop"
                       @if ($isCurrent) aria-current="page" @endif
                       @class([
                           'border-b pb-1',
                           'border-ink text-ink' => $isCurrent,
                           'border-transparent text-muted hover:text-ink' => ! $isCurrent,
                       ])>
                        {{ __('shop.collection.tabs.'.$key) }}
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Toolbar and filter panel are one GET form, so the sort select and
             the panel's fields submit together and the URL ends up holding the
             whole state. The panel opens with a checkbox rather than a script,
             the same way the header's search panel does. --}}
        <form method="GET" action="{{ route('storefront.catalogue', absolute: false) }}" data-catalogue-filters>
            {{-- Carries the active search through a filter change. Without it,
                 narrowing by price would quietly throw the search away. --}}
            <input type="hidden" name="q" value="{{ $filter->q }}">

            <input type="checkbox" id="filter-panel-toggle" class="peer/filters sr-only">

            <div class="mt-6 flex flex-wrap items-center justify-between gap-4 border-y border-rule py-4 font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                <label for="filter-panel-toggle" class="flex cursor-pointer items-center gap-2 hover:text-ink">
                    <svg viewBox="0 0 20 20" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" aria-hidden="true">
                        <line x1="3" y1="6" x2="17" y2="6" />
                        <line x1="3" y1="10" x2="17" y2="10" />
                        <line x1="3" y1="14" x2="17" y2="14" />
                    </svg>
                    <span>{{ __('shop.collection.filters') }}</span>
                    <span>({{ __('shop.collection.count', ['count' => $products->count()]) }})</span>
                </label>

                <div class="flex items-center gap-3">
                    <label for="catalogue-sort">{{ __('shop.collection.sort') }}</label>
                    <select id="catalogue-sort" name="sort"
                            class="border-b border-current bg-transparent py-0.5 pr-1 font-sans text-[11px] uppercase tracking-[0.16em] text-ink">
                        @foreach (\App\Domain\Catalog\CatalogueFilter::SORTS as $option)
                            <option value="{{ $option }}" @selected($filter->sort === $option)>
                                {{ __('shop.collection.sort_options.'.$option) }}
                            </option>
                        @endforeach
                    </select>
                    {{-- The script hides this and submits on change instead. It
                         is visible without one, because a select that needs a
                         script to take effect is a control that does nothing. --}}
                    <button type="submit" data-filters-submit
                            class="border border-border px-3 py-1 text-ink hover:border-ink">
                        {{ __('shop.collection.apply') }}
                    </button>
                </div>
            </div>

            <div class="hidden border-b border-rule py-6 peer-checked/filters:block">
                <div class="grid gap-8 sm:grid-cols-2">
                    <fieldset>
                        <legend class="font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                            {{ __('shop.collection.filter_category') }}
                        </legend>
                        <div class="mt-3 space-y-2">
                            @foreach (['' => 'all', 'wallet' => 'wallet', 'card' => 'card'] as $value => $key)
                                <label class="flex items-center gap-3 font-sans text-[13px] text-ink">
                                    <input type="radio" name="category" value="{{ $value }}"
                                           @checked(($filter->category ?? '') === $value)>
                                    {{ __('shop.collection.tabs.'.$key) }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend class="font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                            {{ __('shop.collection.filter_price') }}
                        </legend>
                        <div class="mt-3 space-y-2">
                            @foreach (['' => 'any', 'under_50' => 'under_50', '50_100' => 'mid', 'over_100' => 'over_100'] as $value => $key)
                                <label class="flex items-center gap-3 font-sans text-[13px] text-ink">
                                    <input type="radio" name="price" value="{{ $value }}"
                                           @checked(($filter->price ?? '') === $value)>
                                    {{ __('shop.collection.price_bands.'.$key) }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </div>

                <div class="mt-6 flex items-center gap-5">
                    <button type="submit"
                            class="bg-ink px-6 py-3 font-sans text-[11px] uppercase tracking-[0.18em] text-ground hover:bg-accent">
                        {{ __('shop.collection.apply') }}
                    </button>
                    @if ($filter->isActive())
                        <a href="{{ route('storefront.catalogue', absolute: false) }}#shop"
                           class="font-sans text-[11px] uppercase tracking-[0.16em] text-muted hover:text-ink">
                            {{ __('shop.collection.clear') }}
                        </a>
                    @endif
                </div>
            </div>
        </form>

        {{-- What the grid is actually showing, when that is not simply
             everything. A bare count leaves someone guessing why a piece they
             expected is missing. --}}
        @if ($filter->q !== null)
            <p class="mt-6 font-sans text-[13px] text-ink-soft">
                {{ __('shop.collection.results_for', ['count' => $products->count(), 'query' => $filter->q]) }}
                <a href="{{ route('storefront.catalogue', $filter->toQuery(['q' => null]), absolute: false) }}#shop"
                   class="ml-3 border-b border-current text-muted hover:text-ink">{{ __('shop.collection.clear_search') }}</a>
            </p>
        @endif

        {{-- An empty shop and a search that found nothing are different
             facts and need different sentences. --}}
        @if ($products->isEmpty())
            <div class="px-6 py-32 text-center font-display text-lg tracking-wide text-muted">
                {{ $totalProductCount === 0 ? __('shop.catalogue.empty') : __('shop.collection.no_matches') }}
            </div>
        @else
            {{-- Four across on a desktop, which is what makes each tile (and so
                 each photograph) smaller. Steps down 4 → 3 → 2 → 1 so the tile
                 never has to carry a product photograph at a width where the
                 name and price stop fitting on one line. --}}
            {{-- 4 → 3 → 2 → 1. The breakpoints live in app.css as .product-grid
                 rather than as Tailwind variants; see the comment there for
                 why four steps that must cascade in order cannot be expressed
                 reliably as arbitrary media variants. --}}
            <div class="product-grid mt-10">
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
