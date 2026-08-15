@props(['product'])

@php
    $image = $product->images->first();
@endphp

<a href="{{ route('storefront.product', ['slug' => $product->slug], absolute: false) }}" class="group block">
    {{-- Fixed aspect ratio with heavy internal padding: the product floats in
         its frame, and the emptiness inside the tile is the effect. --}}
    <div class="flex aspect-[3/4] items-center justify-center bg-tile p-12">
        @if ($image)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->path) }}"
                 alt="{{ $image->alt_text }}"
                 class="max-h-full max-w-full object-contain transition-opacity duration-500 group-hover:opacity-90">
        @else
            {{-- Placeholder holds the ratio so the grid never collapses before
                 real photography arrives. --}}
            <span class="font-serif text-sm tracking-widest text-muted">{{ $product->name }}</span>
        @endif
    </div>

    <div class="py-4 text-center">
        <h2 class="font-serif text-base">{{ $product->name }}</h2>
        <p class="mt-1 font-serif text-sm text-accent"><x-price :minor="$product->base_price_minor" /></p>
    </div>
</a>
