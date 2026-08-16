<div class="mx-auto max-w-3xl px-6 py-16">
    <h1 class="font-display text-3xl tracking-wide">{{ __('shop.cart.title') }}</h1>

    @if ($dropped)
        <p class="mt-6 border border-accent/40 px-4 py-3 font-sans text-sm text-accent">
            {{ __('shop.cart.line_removed') }}
        </p>
    @endif

    @if (empty($lines))
        <p class="mt-16 text-center font-display text-lg text-muted">{{ __('shop.cart.empty') }}</p>
    @else
        <ul class="mt-12 divide-y divide-ink/10">
            @foreach ($lines as $line)
                <li class="flex items-start justify-between py-6">
                    <div>
                        <p class="font-display text-base">{{ $line->productName }}</p>
                        <p class="mt-1 font-sans text-xs text-muted">{{ $line->variantDescription }}</p>

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
                        <p class="font-display text-base"><x-price :minor="$line->lineTotalMinor()" /></p>
                        <button type="button"
                                wire:click="remove('{{ $line->lineKey }}')"
                                class="mt-2 font-sans text-xs uppercase tracking-widest text-muted hover:text-accent">
                            {{ __('shop.cart.remove') }}
                        </button>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-8 flex items-center justify-between border-t border-ink/10 pt-6">
            <span class="font-display text-lg">{{ __('shop.cart.subtotal') }}</span>
            <span class="font-display text-lg"><x-price :minor="$subtotalMinor" /></span>
        </div>

        <a href="{{ route('storefront.checkout', absolute: false) }}"
           class="mt-8 block bg-ink px-6 py-4 text-center font-display text-sm tracking-widest text-ground hover:bg-accent">
            {{ __('shop.cart.checkout') }}
        </a>
    @endif
</div>
