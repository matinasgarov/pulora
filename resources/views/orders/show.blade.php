{{-- See orders/lookup.blade.php: a static cart badge here too, for the same
     byte-identical-response reason and because nothing here mutates the cart. --}}
<x-layouts.storefront :title="__('shop.orders.order_heading', ['number' => $order->order_number])" :live-cart="false">
    <div class="mx-auto max-w-3xl px-6 py-16">
        <h1 class="font-serif text-3xl tracking-wide">
            {{ __('shop.orders.order_heading', ['number' => $order->order_number]) }}
        </h1>

        <p class="mt-4 font-sans text-sm text-muted">
            {{ __('shop.orders.status') }}: {{ $order->status->label() }}
        </p>

        @if ($order->tracking_number)
            <p class="mt-1 font-sans text-sm text-muted">
                {{ __('shop.orders.tracking_number') }}: {{ $order->tracking_number }}
            </p>
        @endif

        <ul class="mt-12 divide-y divide-ink/10">
            @foreach ($order->items as $item)
                <li class="flex items-start justify-between py-6">
                    <div>
                        <p class="font-serif text-base">
                            {{ $item->product_name }} — {{ $item->variant_description }}
                        </p>

                        @if ($item->personalization)
                            <p class="mt-1 font-sans text-xs text-muted">
                                {{ implode(', ', $item->personalization) }}
                            </p>
                        @endif

                        <p class="mt-2 font-sans text-xs text-muted">
                            {{ __('shop.orders.qty') }}: {{ $item->quantity }}
                        </p>
                    </div>

                    <p class="font-serif text-base"><x-price :minor="$item->line_total_minor" /></p>
                </li>
            @endforeach
        </ul>

        <div class="mt-8 space-y-2 border-t border-ink/10 pt-6">
            <div class="flex items-center justify-between font-sans text-sm text-muted">
                <span>{{ __('shop.orders.subtotal') }}</span>
                <x-price :minor="$order->subtotal_minor" />
            </div>

            <div class="flex items-center justify-between font-sans text-sm text-muted">
                <span>{{ __('shop.orders.shipping') }}</span>
                <x-price :minor="$order->shipping_minor" />
            </div>

            @if ($order->discount_minor > 0)
                <div class="flex items-center justify-between font-sans text-sm text-muted">
                    <span>{{ __('shop.orders.discount') }}</span>
                    <span>−<x-price :minor="$order->discount_minor" /></span>
                </div>
            @endif

            <div class="flex items-center justify-between border-t border-ink/10 pt-4 font-serif text-lg">
                <span>{{ __('shop.orders.total') }}</span>
                <x-price :minor="$order->total_minor" />
            </div>
        </div>
    </div>
</x-layouts.storefront>
