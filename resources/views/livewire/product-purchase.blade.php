<div>
    <p class="font-display text-xl text-accent">
        <x-price :minor="$this->unitPriceMinor" />
    </p>

    @if ($product->variants->where('is_active', true)->count() > 1)
        <div class="mt-6 flex flex-wrap gap-2">
            @foreach ($product->variants->where('is_active', true) as $variant)
                <button type="button"
                        wire:click="$set('variantId', {{ $variant->id }})"
                        @class([
                            'border px-4 py-2 font-display text-sm tracking-wide',
                            'border-accent text-accent' => $variantId === $variant->id,
                            'border-ink/20 hover:border-ink/40' => $variantId !== $variant->id,
                        ])>
                    {{ $variant->description }}
                </button>
            @endforeach
        </div>
    @endif

    @foreach ($product->personalizationOptions as $option)
        <div class="mt-6">
            <label class="block font-sans text-xs uppercase tracking-widest text-muted">
                {{ $option->label }}
            </label>
            <input type="text"
                   wire:model.live="personalization.{{ $option->type }}"
                   maxlength="{{ $option->max_characters }}"
                   class="mt-2 w-full border border-ink/20 bg-transparent px-3 py-2 font-sans">
            @error('personalization.'.$option->type)
                <p class="mt-1 font-sans text-xs text-accent">{{ $message }}</p>
            @enderror
        </div>
    @endforeach

    <p class="mt-6 font-sans text-xs tracking-wide text-muted">
        {{ __('shop.product.made_to_order', ['days' => $product->lead_time_days]) }}
    </p>

    @if ($this->available)
        <button type="button" wire:click="add"
                class="mt-6 w-full bg-ink px-6 py-4 font-display text-sm tracking-widest text-ground hover:bg-accent">
            {{ __('shop.product.add_to_cart') }}
        </button>
    @else
        <p class="mt-6 border border-ink/20 px-6 py-4 text-center font-display text-sm tracking-widest text-muted">
            {{ __('shop.product.unavailable') }}
        </p>
    @endif
</div>
