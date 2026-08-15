<x-layouts.storefront>
    <div class="px-6 py-24 text-center">
        <h1 class="font-serif text-4xl tracking-wide">{{ __('shop.catalogue.title') }}</h1>
        @foreach (\App\Domain\Catalog\Models\Product::where('is_active', true)->get() as $product)
            <p class="mt-4"><x-price :minor="$product->base_price_minor" /></p>
        @endforeach
    </div>
</x-layouts.storefront>
