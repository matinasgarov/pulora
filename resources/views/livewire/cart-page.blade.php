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
                <li class="flex gap-5 py-6">
                    <x-cart-line-thumb :path="$line->imagePath" :name="$line->productName" />

                    <div class="flex min-w-0 flex-1 flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="font-display text-base text-ink">{{ $line->productName }}</p>
                            <p class="mt-1 font-sans text-[11px] uppercase tracking-[0.1em] text-muted">{{ $line->variantDescription }}</p>

                            @foreach ($line->personalization as $key => $value)
                                <p class="mt-1 font-sans text-xs text-muted">
                                    {{ \Illuminate\Support\Str::of($key)->headline() }}: {{ $value }}
                                </p>
                            @endforeach

                            {{-- Stepper. The minus is a real decrement at every
                                 quantity: at 1 it removes the line, because a
                                 control that can reach zero is the same gesture
                                 as removing and disabling it there just leaves
                                 people stuck pressing a dead button. --}}
                            <div class="mt-3 flex items-center gap-3">
                                <div class="inline-flex items-center border border-border">
                                    <button type="button"
                                            wire:click="step('{{ $line->lineKey }}', -1)"
                                            class="grid size-9 place-items-center text-ink hover:bg-rule-light"
                                            aria-label="{{ __('shop.cart.decrease') }}">
                                        <svg viewBox="0 0 20 20" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" aria-hidden="true">
                                            <line x1="5" y1="10" x2="15" y2="10" />
                                        </svg>
                                    </button>

                                    <span class="min-w-9 px-1 text-center font-sans text-[14px] text-ink"
                                          aria-live="polite"
                                          aria-label="{{ __('shop.cart.quantity') }}">{{ $line->quantity }}</span>

                                    <button type="button"
                                            wire:click="step('{{ $line->lineKey }}', 1)"
                                            class="grid size-9 place-items-center text-ink hover:bg-rule-light"
                                            aria-label="{{ __('shop.cart.increase') }}">
                                        <svg viewBox="0 0 20 20" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" aria-hidden="true">
                                            <line x1="5" y1="10" x2="15" y2="10" />
                                            <line x1="10" y1="5" x2="10" y2="15" />
                                        </svg>
                                    </button>
                                </div>

                                <button type="button"
                                        wire:click="remove('{{ $line->lineKey }}')"
                                        class="grid size-9 place-items-center text-muted hover:text-danger"
                                        aria-label="{{ __('shop.cart.remove') }}">
                                    <svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M4 6h12" />
                                        <path d="M8 6V4.5h4V6" />
                                        <path d="M5.75 6l.75 9.5h7l.75-9.5" />
                                        <line x1="8.5" y1="8.75" x2="8.75" y2="13" />
                                        <line x1="11.5" y1="8.75" x2="11.25" y2="13" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <p class="shrink-0 font-display text-base text-ink sm:text-right">
                            <x-price :minor="$line->lineTotalMinor()" />
                        </p>
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
