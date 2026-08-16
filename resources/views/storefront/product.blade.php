<x-layouts.storefront :title="$product->name">
    <div class="grid grid-cols-1 lg:grid-cols-2">
        <div class="flex aspect-[3/4] items-center justify-center bg-ground-alt p-16">
            @if ($image = $product->images->first())
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->path) }}"
                     alt="{{ $image->alt_text }}"
                     class="max-h-full max-w-full object-contain">
            @else
                <span class="font-display text-sm tracking-widest text-muted">{{ $product->name }}</span>
            @endif
        </div>

        <div class="px-8 py-16 lg:px-16">
            <h1 class="font-display text-3xl tracking-wide">{{ $product->name }}</h1>

            @if ($product->description)
                <p class="mt-6 font-sans text-sm leading-relaxed text-muted">{{ $product->description }}</p>
            @endif

            <livewire:product-purchase :product="$product" />

            @if ($product->story)
                <div class="mt-16 border-t border-ink/10 pt-8">
                    <p class="font-display text-sm leading-relaxed">{{ $product->story }}</p>
                </div>
            @endif
        </div>
    </div>
</x-layouts.storefront>
