<div class="mx-auto max-w-3xl px-4 py-16 sm:px-11">
    <h1 class="font-display text-3xl tracking-wide text-ink">{{ __('shop.cart.title') }}</h1>

    @if ($dropped)
        <p class="mt-6 border border-accent/40 px-4 py-3 font-sans text-sm text-accent">
            {{ __('shop.cart.line_removed') }}
        </p>
    @endif

    @if (empty($lines))
        <p class="mt-16 text-center font-display text-lg text-muted">{{ __('shop.cart.empty') }}</p>
    @else
        <ul class="mt-12 divide-y divide-rule">
            @foreach ($lines as $line)
                <li class="flex flex-col items-start justify-between gap-4 py-6 sm:flex-row">
                    <div>
                        <p class="font-display text-base text-ink">{{ $line->productName }}</p>
                        <p class="mt-1 font-sans text-[11px] uppercase tracking-[0.1em] text-muted">{{ $line->variantDescription }}</p>

                        @foreach ($line->personalization as $key => $value)
                            <p class="mt-1 font-sans text-xs text-muted">
                                {{ \Illuminate\Support\Str::of($key)->headline() }}: {{ $value }}
                            </p>
                        @endforeach

                        <p class="mt-2 font-sans text-xs text-muted">
                            {{ __('shop.cart.quantity') }}: {{ $line->quantity }}
                        </p>
                    </div>

                    <div class="text-right">
                        <p class="font-display text-base text-ink"><x-price :minor="$line->lineTotalMinor()" /></p>
                        <button type="button"
                                wire:click="remove('{{ $line->lineKey }}')"
                                class="mt-2 font-sans text-[11px] uppercase tracking-[0.14em] text-muted hover:text-accent">
                            {{ __('shop.cart.remove') }}
                        </button>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-8 flex items-center justify-between border-t border-rule pt-6">
            <span class="font-display text-lg text-ink">{{ __('shop.cart.subtotal') }}</span>
            <span class="font-display text-lg text-ink"><x-price :minor="$subtotalMinor" /></span>
        </div>

        <a href="{{ route('storefront.checkout', absolute: false) }}"
           class="mt-8 block w-full bg-ink px-6 py-[19px] text-center font-sans text-[11px] uppercase tracking-[0.18em] text-ground hover:bg-accent">
            {{ __('shop.cart.checkout') }}
        </a>
    @endif
</div>
