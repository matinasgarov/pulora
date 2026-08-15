<x-layouts.storefront :title="__('shop.catalogue.title')">
    @if ($products->isEmpty())
        <div class="px-6 py-32 text-center font-serif text-lg tracking-wide text-muted">
            {{ __('shop.catalogue.empty') }}
        </div>
    @else
        {{-- Edge to edge, hairline gutters. The white space lives inside the tiles. --}}
        <div class="grid grid-cols-1 gap-px sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($products as $product)
                <x-product-tile :product="$product" />
            @endforeach
        </div>
    @endif
</x-layouts.storefront>
