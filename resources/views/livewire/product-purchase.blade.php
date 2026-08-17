<div>
    {{-- Price + leather, baseline row, 34px below the H1 in product.blade.php. --}}
    <div class="mt-[34px] flex items-baseline justify-between gap-4 font-sans">
        <span class="text-[16px] text-ink">
            <x-price :minor="$this->unitPriceMinor" />
        </span>
        @if ($product->leather)
            <span class="shrink-0 text-[11px] uppercase tracking-[0.1em] text-muted">{{ $product->leather }}</span>
        @endif
    </div>

    @if ($product->description)
        <p class="mt-6 text-[14px] leading-[1.85] text-ink-soft">{{ $product->description }}</p>
    @endif

    @if ($product->variants->where('is_active', true)->count() > 0)
        <div class="mt-8">
            <p class="text-[11px] uppercase tracking-[0.16em] text-muted">{{ __('shop.product.colour') }}</p>

            <div class="mt-3 flex flex-wrap items-center gap-1">
                @foreach ($product->variants->where('is_active', true) as $variant)
                    @php $hex = \App\Support\SwatchColour::hex($variant->description); @endphp

                    @if ($hex)
                        {{-- 34px visible swatch inside a 44px hit area, so the
                             design's 34px circle doesn't fall below the
                             44px touch-target floor on mobile. --}}
                        <button type="button"
                                wire:click="$set('variantId', {{ $variant->id }})"
                                aria-label="{{ $variant->description }}"
                                aria-pressed="{{ $variantId === $variant->id ? 'true' : 'false' }}"
                                class="flex h-11 w-11 items-center justify-center">
                            <span class="block h-[34px] w-[34px] rounded-full border border-border"
                                  style="background-color: {{ $hex }};{{ $variantId === $variant->id ? ' outline: 1px solid var(--color-ink); outline-offset: 3px;' : '' }}"></span>
                        </button>
                    @else
                        <button type="button"
                                wire:click="$set('variantId', {{ $variant->id }})"
                                @class([
                                    'flex min-h-11 items-center justify-center border px-4 font-sans text-[11px] uppercase tracking-[0.1em]',
                                    'border-ink text-ink' => $variantId === $variant->id,
                                    'border-border text-muted hover:border-ink/40' => $variantId !== $variant->id,
                                ])>
                            {{ $variant->description }}
                        </button>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    @foreach ($product->personalizationOptions as $option)
        <div class="mt-8">
            <label class="block text-[11px] uppercase tracking-[0.16em] text-muted">
                {{ $option->label }}
            </label>
            <input type="text"
                   wire:model.live="personalization.{{ $option->type }}"
                   maxlength="{{ $option->max_characters }}"
                   @error('personalization.'.$option->type) aria-invalid="true" @enderror
                   class="mt-2 w-full border border-border bg-transparent px-3 py-3 font-sans text-[14px] aria-[invalid]:border-danger">
            @error('personalization.'.$option->type)
                <p class="mt-1 font-sans text-xs text-danger">{{ $message }}</p>
            @enderror
        </div>
    @endforeach

    <p class="mt-6 font-sans text-xs tracking-wide text-muted">
        {{ __('shop.product.made_to_order', ['days' => $product->lead_time_days]) }}
    </p>

    @if ($this->available)
        <button type="button" wire:click="add"
                class="mt-6 w-full bg-ink px-6 py-[19px] font-sans text-[11px] uppercase tracking-[0.18em] text-ground hover:bg-accent">
            {{ __('shop.product.add_to_cart') }}
        </button>
    @else
        <p class="mt-6 w-full border border-border px-6 py-[19px] text-center font-sans text-[11px] uppercase tracking-[0.18em] text-muted">
            {{ __('shop.product.unavailable') }}
        </p>
    @endif
</div>
