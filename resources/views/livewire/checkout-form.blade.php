<div class="mx-auto max-w-3xl px-6 py-16">
    <h1 class="font-serif text-3xl tracking-wide">{{ __('shop.checkout.title') }}</h1>

    <form wire:submit="submit" class="mt-12 space-y-6">
        <div>
            <label class="block font-sans text-xs uppercase tracking-widest text-muted">
                {{ __('shop.checkout.email') }}
            </label>
            <input type="email" wire:model="email"
                   class="mt-2 w-full border border-ink/20 bg-transparent px-3 py-2 font-sans">
            @error('email') <p class="mt-1 font-sans text-xs text-accent">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block font-sans text-xs uppercase tracking-widest text-muted">
                {{ __('shop.checkout.name') }}
            </label>
            <input type="text" wire:model="name"
                   class="mt-2 w-full border border-ink/20 bg-transparent px-3 py-2 font-sans">
            @error('name') <p class="mt-1 font-sans text-xs text-accent">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block font-sans text-xs uppercase tracking-widest text-muted">
                {{ __('shop.checkout.phone') }}
            </label>
            <input type="text" wire:model="phone"
                   class="mt-2 w-full border border-ink/20 bg-transparent px-3 py-2 font-sans">
        </div>

        <div>
            <label class="block font-sans text-xs uppercase tracking-widest text-muted">
                {{ __('shop.checkout.address_line1') }}
            </label>
            <input type="text" wire:model="address_line1"
                   class="mt-2 w-full border border-ink/20 bg-transparent px-3 py-2 font-sans">
            @error('address_line1') <p class="mt-1 font-sans text-xs text-accent">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block font-sans text-xs uppercase tracking-widest text-muted">
                {{ __('shop.checkout.address_line2') }}
            </label>
            <input type="text" wire:model="address_line2"
                   class="mt-2 w-full border border-ink/20 bg-transparent px-3 py-2 font-sans">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block font-sans text-xs uppercase tracking-widest text-muted">
                    {{ __('shop.checkout.city') }}
                </label>
                <input type="text" wire:model="city"
                       class="mt-2 w-full border border-ink/20 bg-transparent px-3 py-2 font-sans">
                @error('city') <p class="mt-1 font-sans text-xs text-accent">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block font-sans text-xs uppercase tracking-widest text-muted">
                    {{ __('shop.checkout.postcode') }}
                </label>
                <input type="text" wire:model="postcode"
                       class="mt-2 w-full border border-ink/20 bg-transparent px-3 py-2 font-sans">
            </div>
        </div>

        <div>
            <label class="block font-sans text-xs uppercase tracking-widest text-muted">
                {{ __('shop.checkout.country') }}
            </label>
            <input type="text" wire:model.live="country_code" maxlength="2"
                   class="mt-2 w-32 border border-ink/20 bg-transparent px-3 py-2 font-sans uppercase">
            @error('country_code') <p class="mt-1 font-sans text-xs text-accent">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block font-sans text-xs uppercase tracking-widest text-muted">
                {{ __('shop.checkout.shipping') }}
            </label>

            @if (empty($quotes))
                <p class="mt-2 font-sans text-sm text-muted">{{ __('shop.checkout.no_shipping') }}</p>
            @else
                <div class="mt-2 space-y-2">
                    @foreach ($quotes as $quote)
                        <label class="flex items-center justify-between border border-ink/20 px-4 py-3 font-sans text-sm">
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
            <label class="block font-sans text-xs uppercase tracking-widest text-muted">
                {{ __('shop.checkout.discount_code') }}
            </label>
            <input type="text" wire:model="discount_code"
                   class="mt-2 w-full border border-ink/20 bg-transparent px-3 py-2 font-sans">
            @error('discount_code') <p class="mt-1 font-sans text-xs text-accent">{{ $message }}</p> @enderror
        </div>

        @error('cart') <p class="font-sans text-xs text-accent">{{ $message }}</p> @enderror
        @error('payment') <p class="font-sans text-xs text-accent">{{ $message }}</p> @enderror

        <div class="flex items-center justify-between border-t border-ink/10 pt-6">
            <span class="font-serif text-lg">{{ __('shop.checkout.total') }}</span>
            <span class="font-serif text-lg"><x-price :minor="$totalMinor" /></span>
        </div>

        <button type="submit"
                class="mt-4 block w-full bg-ink px-6 py-4 text-center font-serif text-sm tracking-widest text-ground hover:bg-accent">
            {{ __('shop.checkout.place_order') }}
        </button>
    </form>
</div>
