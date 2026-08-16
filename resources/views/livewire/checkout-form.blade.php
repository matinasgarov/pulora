<div class="mx-auto max-w-3xl px-4 py-16 sm:px-11">
    <h1 class="font-display text-3xl tracking-wide text-ink">{{ __('shop.checkout.title') }}</h1>

    <form wire:submit="submit" class="mt-12 space-y-6">
        <div>
            <label class="block font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                {{ __('shop.checkout.email') }}
            </label>
            <input type="email" wire:model="email"
                   class="mt-2 w-full border border-border bg-transparent px-3 py-3 font-sans text-[14px]">
            @error('email') <p class="mt-1 font-sans text-xs text-accent">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                {{ __('shop.checkout.name') }}
            </label>
            <input type="text" wire:model="name"
                   class="mt-2 w-full border border-border bg-transparent px-3 py-3 font-sans text-[14px]">
            @error('name') <p class="mt-1 font-sans text-xs text-accent">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                {{ __('shop.checkout.phone') }}
            </label>
            <input type="text" wire:model="phone"
                   class="mt-2 w-full border border-border bg-transparent px-3 py-3 font-sans text-[14px]">
        </div>

        <div>
            <label class="block font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                {{ __('shop.checkout.address_line1') }}
            </label>
            <input type="text" wire:model="address_line1"
                   class="mt-2 w-full border border-border bg-transparent px-3 py-3 font-sans text-[14px]">
            @error('address_line1') <p class="mt-1 font-sans text-xs text-accent">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                {{ __('shop.checkout.address_line2') }}
            </label>
            <input type="text" wire:model="address_line2"
                   class="mt-2 w-full border border-border bg-transparent px-3 py-3 font-sans text-[14px]">
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="block font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                    {{ __('shop.checkout.city') }}
                </label>
                <input type="text" wire:model="city"
                       class="mt-2 w-full border border-border bg-transparent px-3 py-3 font-sans text-[14px]">
                @error('city') <p class="mt-1 font-sans text-xs text-accent">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                    {{ __('shop.checkout.postcode') }}
                </label>
                <input type="text" wire:model="postcode"
                       class="mt-2 w-full border border-border bg-transparent px-3 py-3 font-sans text-[14px]">
            </div>
        </div>

        <div>
            <label class="block font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                {{ __('shop.checkout.country') }}
            </label>
            <input type="text" wire:model.live="country_code" maxlength="2"
                   class="mt-2 w-32 border border-border bg-transparent px-3 py-3 font-sans text-[14px] uppercase">
            @error('country_code') <p class="mt-1 font-sans text-xs text-accent">{{ $message }}</p> @enderror
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
                        <label class="flex items-center justify-between border border-border px-4 py-3 font-sans text-sm text-ink">
                            <span class="flex items-center gap-3">
                                <input type="radio" wire:model="shipping_rate_id" value="{{ $quote['rateId'] }}">
                                {{ $quote['name'] }}
                            </span>
                            <x-price :minor="$quote['priceMinor']" />
                        </label>
                    @endforeach
                </div>
            @endif
            @error('shipping_rate_id') <p class="mt-1 font-sans text-xs text-accent">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                {{ __('shop.checkout.discount_code') }}
            </label>
            <input type="text" wire:model="discount_code"
                   class="mt-2 w-full border border-border bg-transparent px-3 py-3 font-sans text-[14px]">
            @error('discount_code') <p class="mt-1 font-sans text-xs text-accent">{{ $message }}</p> @enderror
        </div>

        @error('cart') <p class="font-sans text-xs text-accent">{{ $message }}</p> @enderror
        @error('payment') <p class="font-sans text-xs text-accent">{{ $message }}</p> @enderror

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
