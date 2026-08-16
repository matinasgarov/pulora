<x-layouts.storefront :title="$product->name">
    <nav class="px-4 py-[22px] font-sans text-[10px] uppercase tracking-[0.14em] text-muted sm:px-11">
        <a href="{{ route('storefront.catalogue') }}" class="hover:text-ink">{{ __('shop.nav.catalogue') }}</a>
        <span class="mx-2" aria-hidden="true">/</span>
        <span class="text-ink">{{ $product->name }}</span>
    </nav>

    <div class="grid grid-cols-1 gap-10 px-4 sm:px-11 lg:grid-cols-[1.35fr_1fr] lg:items-start lg:gap-16">
        {{-- Gallery — 2-col grid at lg, 10px gap, hero (1) and in-hand (4)
             spanning both columns. Below lg it becomes a horizontal
             scroller with snap points rather than a squeezed 2-col grid.
             Missing images fall back to the placeholder frame at the right
             ratio, from product_images in sort_order. --}}
        @php
            $images = $product->images;
            $gallerySlots = [
                [
                    'image' => $images->get(0),
                    'caption' => __('shop.placeholder.gallery.hero', ['name' => $product->name]),
                    'ratio' => 'aspect-square',
                    'span' => 'lg:col-span-2',
                ],
                [
                    'image' => $images->get(1),
                    'caption' => __('shop.placeholder.gallery.edge_paint', ['name' => $product->name]),
                    'ratio' => 'aspect-square',
                    'span' => '',
                ],
                [
                    'image' => $images->get(2),
                    'caption' => __('shop.placeholder.gallery.interior', ['name' => $product->name]),
                    'ratio' => 'aspect-square',
                    'span' => '',
                ],
                [
                    'image' => $images->get(3),
                    'caption' => __('shop.placeholder.gallery.in_hand', ['name' => $product->name]),
                    'ratio' => 'aspect-[3/2]',
                    'span' => 'lg:col-span-2',
                ],
            ];
        @endphp

        <div class="-mx-4 flex snap-x snap-mandatory gap-[10px] overflow-x-auto px-4 pb-1 sm:-mx-11 sm:px-11 lg:mx-0 lg:grid lg:grid-cols-2 lg:gap-[10px] lg:overflow-visible lg:px-0 lg:pb-0">
            @foreach ($gallerySlots as $slot)
                <div class="w-[82%] shrink-0 snap-start overflow-hidden bg-ground-alt {{ $slot['ratio'] }} {{ $slot['span'] }} lg:w-auto">
                    @if ($slot['image'])
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($slot['image']->path) }}"
                             alt="{{ $slot['image']->alt_text }}"
                             class="h-full w-full object-cover">
                    @else
                        <x-placeholder-frame :caption="$slot['caption']" inset-class="top-3 right-3" class="h-full w-full" />
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Buy column. --}}
        <div class="lg:sticky lg:top-[130px]">
            <h1 class="font-display text-[38px] leading-[1.15] text-ink">{{ $product->name }}</h1>

            <livewire:product-purchase :product="$product" />

            {{-- Not part of the design spec's buy-column list, but existing
                 content (Product::$story) that must keep rendering. --}}
            @if ($product->story)
                <p class="mt-6 font-sans text-[13px] leading-[1.85] text-ink-soft">{{ $product->story }}</p>
            @endif

            {{-- Phase 3 builds the bespoke configurator; until then this
                 points wherever the homepage's bespoke CTA points, via the
                 shared BespokeCta::href() helper. --}}
            <a href="{{ $bespokeCtaHref }}"
               class="mt-4 block w-full border border-ink px-6 py-[19px] text-center font-sans text-[11px] uppercase tracking-[0.18em] text-ink hover:border-accent hover:text-accent">
                {{ __('shop.product.bespoke_cta') }}
            </a>

            {{-- Trust list — identical on every product, so the copy lives
                 in the lang files, not on the model. --}}
            <ul class="mt-[26px] border-t border-rule pt-[26px]">
                @foreach (__('shop.product.trust') as $line)
                    <li class="flex items-start gap-3 font-sans text-[13px] text-ink-soft [&+&]:mt-4">
                        <span class="mt-[7px] h-[5px] w-[5px] shrink-0 rounded-full bg-accent" aria-hidden="true"></span>
                        <span>{{ $line }}</span>
                    </li>
                @endforeach
            </ul>

            @if (count($product->specs) > 0)
                <div data-product-specs class="mt-2">
                    @foreach ($product->specs as $spec)
                        <div class="flex justify-between gap-4 border-b border-rule-light py-[14px] font-sans text-[13px]">
                            <span class="text-muted">{{ $spec['label'] }}</span>
                            <span class="text-ink">{{ $spec['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Related — three tiles, excluding the current product. --}}
    @if ($related->isNotEmpty())
        <section class="mt-[120px] border-t border-rule px-4 pt-16 sm:px-11">
            <h2 class="font-display text-[13px] uppercase tracking-[0.28em] text-ink">
                {{ __('shop.product.related') }}
            </h2>

            <div class="mt-10 grid grid-cols-1 gap-x-10 gap-y-[72px] [@media(min-width:560px)]:grid-cols-2 [@media(min-width:900px)]:grid-cols-3">
                @foreach ($related as $item)
                    <x-product-tile :product="$item" />
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.storefront>
