@props(['product'])

@php
    $image = $product->images->first();
    $productUrl = route('storefront.product', ['slug' => $product->slug], absolute: false);
    // canQuickAdd() reads $product->variants and $product->personalizationOptions
    // in memory — both are eager-loaded by CatalogueController so this never
    // issues a query per tile.
    $canQuickAdd = $product->canQuickAdd();
@endphp

<div class="group">
    {{-- 4/5 frame, overflow hidden. The badge and quick-add bar sit on top of
         (not inside) the link, so the quick-add button can intercept clicks
         instead of the whole frame navigating away. --}}
    <div class="relative aspect-[4/5] overflow-hidden bg-ground-alt">
        <a href="{{ $productUrl }}" class="absolute inset-0 block">
            @if ($image)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->path) }}"
                     alt="{{ $image->alt_text }}"
                     class="h-full w-full object-cover transition-opacity duration-500 group-hover:opacity-90">
            @else
                <x-placeholder-frame
                    :caption="__('shop.placeholder.tile', ['name' => $product->name])"
                    inset-class="top-3 right-3"
                    class="h-full w-full" />
            @endif
        </a>

        @if ($product->tag)
            <span class="pointer-events-none absolute left-3 top-3 z-10 bg-accent px-2 py-1 font-sans text-[9px] uppercase tracking-[0.2em] text-ground">
                {{ $product->tag->label() }}
            </span>
        @endif

        {{-- Wishlist heart is Phase 2 and deliberately omitted — a dead
             heart is worse than shipping none. --}}

        @if ($canQuickAdd)
            <livewire:quick-add :product="$product" :key="'quick-add-'.$product->id" />
        @endif
    </div>

    <div class="mt-[18px] flex items-baseline justify-between gap-3">
        <div>
            <h2 class="font-sans text-[13px] text-ink">
                <a href="{{ $productUrl }}" class="hover:text-accent">{{ $product->name }}</a>
            </h2>
            @if ($product->leather)
                <p class="mt-[5px] font-sans text-[11px] uppercase tracking-[0.1em] text-muted">{{ $product->leather }}</p>
            @endif
        </div>
        <p class="shrink-0 font-sans text-[13px] text-ink"><x-price :minor="$product->base_price_minor" /></p>
    </div>
</div>
