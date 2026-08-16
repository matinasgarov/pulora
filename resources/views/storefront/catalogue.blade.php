<x-layouts.storefront :title="__('shop.catalogue.title')">
    @if ($products->isEmpty())
        <div class="px-6 py-32 text-center font-display text-lg tracking-wide text-muted">
            {{ __('shop.catalogue.empty') }}
        </div>
    @else
        {{-- Edge to edge, hairline gutters. The white space lives inside the tiles.

             auto-fit rather than a fixed lg:grid-cols-4: this shop sells a
             handful of made-to-order pieces, so the catalogue is usually
             smaller than the column count. Four fixed columns holding three
             products left the row stopping dead at three-quarters width with a
             bare quarter beside it, which reads as a broken layout rather than
             restraint. auto-fit collapses the empty track, so three products
             fill the row and eight still land four across. --}}
        <div class="grid gap-px [grid-template-columns:repeat(auto-fit,minmax(min(100%,20rem),1fr))]">
            @foreach ($products as $product)
                <x-product-tile :product="$product" />
            @endforeach
        </div>
    @endif
</x-layouts.storefront>
