@php
    // One rule for every text input. The red border is driven by aria-invalid
    // rather than by a second @error check, so the visible state and the state
    // announced to a screen reader can never disagree — and colour is never
    // the only carrier of "this field is wrong".
    $field = 'mt-2 w-full border border-border bg-transparent px-3 py-3 font-sans text-[14px] aria-[invalid]:border-danger';
    $errorText = 'mt-1 font-sans text-xs text-danger';
@endphp

<div class="mx-auto max-w-3xl px-4 py-16 sm:px-11">
    <h1 class="font-display text-3xl tracking-wide text-ink">{{ __('shop.checkout.title') }}</h1>

    {{-- What is being paid for, with the photographs. Checkout previously showed
         a total and nothing else, which asks someone to hand over an address on
         the strength of a number. Read from the same snapshot the total is
         computed from, so the two cannot disagree. --}}
    @if ($snapshot->lines !== [])
        <ul class="mt-10 divide-y divide-rule border-y border-rule">
            @foreach ($snapshot->lines as $line)
                <li class="flex items-center gap-4 py-4">
                    <x-cart-line-thumb :path="$line->imagePath" :name="$line->productName" size="w-[56px]" />

                    <div class="min-w-0 flex-1">
                        <p class="font-sans text-[14px] text-ink">{{ $line->productName }}</p>
                        <p class="mt-0.5 font-sans text-[11px] uppercase tracking-[0.1em] text-muted">
                            {{ $line->variantDescription }} · {{ __('shop.cart.quantity') }} {{ $line->quantity }}
                        </p>
                    </div>

                    <p class="shrink-0 font-sans text-[14px] text-ink"><x-price :minor="$line->lineTotalMinor()" /></p>
                </li>
            @endforeach
        </ul>
    @endif

    <form wire:submit="submit" class="mt-12 space-y-6">
        <div>
            <label class="block font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                {{ __('shop.checkout.email') }}
            </label>
            <input type="email" wire:model="email" @error('email') aria-invalid="true" @enderror
                   class="{{ $field }}">
            @error('email') <p class="{{ $errorText }}">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                {{ __('shop.checkout.name') }}
            </label>
            <input type="text" wire:model="name" @error('name') aria-invalid="true" @enderror
                   class="{{ $field }}">
            @error('name') <p class="{{ $errorText }}">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                {{ __('shop.checkout.phone') }}
            </label>
            <input type="text" wire:model="phone" @error('phone') aria-invalid="true" @enderror
                   class="{{ $field }}">
            @error('phone') <p class="{{ $errorText }}">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                {{ __('shop.checkout.address_line1') }}
            </label>
            <input type="text" wire:model="address_line1" @error('address_line1') aria-invalid="true" @enderror
                   class="{{ $field }}">
            @error('address_line1') <p class="{{ $errorText }}">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                {{ __('shop.checkout.address_line2') }}
            </label>
            <input type="text" wire:model="address_line2" @error('address_line2') aria-invalid="true" @enderror
                   class="{{ $field }}">
            @error('address_line2') <p class="{{ $errorText }}">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="block font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                    {{ __('shop.checkout.city') }}
                </label>
                <input type="text" wire:model="city" @error('city') aria-invalid="true" @enderror
                       class="{{ $field }}">
                @error('city') <p class="{{ $errorText }}">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                    {{ __('shop.checkout.postcode') }}
                </label>
                <input type="text" wire:model="postcode" @error('postcode') aria-invalid="true" @enderror
                       class="{{ $field }}">
                @error('postcode') <p class="{{ $errorText }}">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                {{ __('shop.checkout.country') }}
            </label>
            <input type="text" wire:model.live="country_code" maxlength="2" @error('country_code') aria-invalid="true" @enderror
                   class="{{ $field }} w-32 uppercase">
            @error('country_code') <p class="{{ $errorText }}">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                {{ __('shop.checkout.shipping') }}
            </label>

            @if (empty($quotes))
                <p class="mt-2 font-sans text-sm text-muted">{{ __('shop.checkout.no_shipping') }}</p>
            @else
                <div class="mt-2 space-y-2">
                    @foreach ($quotes as $quote)
                        <label class="flex items-center justify-between border border-border px-4 py-3 font-sans text-sm text-ink @error('shipping_rate_id') border-danger @enderror">
                            <span class="flex items-center gap-3">
                                <input type="radio" wire:model="shipping_rate_id" value="{{ $quote['rateId'] }}">
                                {{ $quote['name'] }}
                            </span>
                            <x-price :minor="$quote['priceMinor']" />
                        </label>
                    @endforeach
                </div>
            @endif
            @error('shipping_rate_id') <p class="{{ $errorText }}">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                {{ __('shop.checkout.discount_code') }}
            </label>
            <input type="text" wire:model="discount_code" @error('discount_code') aria-invalid="true" @enderror
                   class="{{ $field }}">
            @error('discount_code') <p class="{{ $errorText }}">{{ $message }}</p> @enderror
        </div>

        @error('cart') <p class="font-sans text-xs text-danger">{{ $message }}</p> @enderror
        @error('payment') <p class="font-sans text-xs text-danger">{{ $message }}</p> @enderror

        <div class="flex items-center justify-between border-t border-rule pt-6">
            <span class="font-display text-lg text-ink">{{ __('shop.checkout.total') }}</span>
            <span class="font-display text-lg text-ink"><x-price :minor="$totalMinor" /></span>
        </div>

        <button type="submit"
                class="mt-4 block w-full bg-ink px-6 py-[19px] text-center font-sans text-[11px] uppercase tracking-[0.18em] text-ground hover:bg-accent">
            {{ __('shop.checkout.place_order') }}
        </button>
    </form>
</div>
