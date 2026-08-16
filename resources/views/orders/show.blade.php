{{-- See orders/lookup.blade.php: a static cart badge here too, for the same
     byte-identical-response reason and because nothing here mutates the cart. --}}
<x-layouts.storefront :title="__('shop.orders.order_heading', ['number' => $order->order_number])" :live-cart="false">
    <div class="mx-auto max-w-3xl px-4 py-16 sm:px-11">
        <h1 class="font-display text-3xl tracking-wide text-ink">
            {{ __('shop.orders.order_heading', ['number' => $order->order_number]) }}
        </h1>

        <p class="mt-4 font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
            {{ __('shop.orders.status') }}: {{ $order->status->label() }}
        </p>

        @if ($order->tracking_number)
            <p class="mt-1 font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                {{ __('shop.orders.tracking_number') }}: {{ $order->tracking_number }}
            </p>
        @endif

        <ul class="mt-12 divide-y divide-rule">
            @foreach ($order->items as $item)
                <li class="flex items-start justify-between gap-4 py-6">
                    <div>
                        <p class="font-display text-base text-ink">
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

                    <p class="font-display text-base text-ink"><x-price :minor="$item->line_total_minor" /></p>
                </li>
            @endforeach
        </ul>

        <div class="mt-8 space-y-[14px] border-t border-rule pt-6">
            <div class="flex items-center justify-between border-b border-rule-light pb-[14px] font-sans text-[13px]">
                <span class="text-muted">{{ __('shop.orders.subtotal') }}</span>
                <span class="text-ink"><x-price :minor="$order->subtotal_minor" /></span>
            </div>

            <div class="flex items-center justify-between border-b border-rule-light pb-[14px] font-sans text-[13px]">
                <span class="text-muted">{{ __('shop.orders.shipping') }}</span>
                <span class="text-ink"><x-price :minor="$order->shipping_minor" /></span>
            </div>

            @if ($order->discount_minor > 0)
                <div class="flex items-center justify-between border-b border-rule-light pb-[14px] font-sans text-[13px]">
                    <span class="text-muted">{{ __('shop.orders.discount') }}</span>
                    <span class="text-ink">−<x-price :minor="$order->discount_minor" /></span>
                </div>
            @endif

            <div class="flex items-center justify-between border-t border-rule pt-4 font-display text-lg text-ink">
                <span>{{ __('shop.orders.total') }}</span>
                <x-price :minor="$order->total_minor" />
            </div>
        </div>
    </div>
</x-layouts.storefront>
