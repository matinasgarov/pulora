<x-layouts.storefront :title="__('shop.orders.find_title')" :live-cart="false">
    <div class="mx-auto max-w-lg px-6 py-16">
        <h1 class="font-serif text-3xl tracking-wide">{{ __('shop.orders.find_title') }}</h1>

        @if (! empty($notFound))
            <p class="mt-6 border border-accent/40 px-4 py-3 font-sans text-sm text-accent">
                {{ __('shop.orders.not_found') }}
            </p>
        @endif

        <form method="POST" action="{{ route('orders.lookup.find') }}" class="mt-12 space-y-6">
            @csrf
            <div>
                <label class="block font-sans text-xs uppercase tracking-widest text-muted">
                    {{ __('shop.orders.email') }}
                </label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       class="mt-2 w-full border border-ink/20 bg-transparent px-3 py-2 font-sans">
            </div>

            <div>
                <label class="block font-sans text-xs uppercase tracking-widest text-muted">
                    {{ __('shop.orders.order_number') }}
                </label>
                <input type="text" name="order_number" value="{{ old('order_number') }}" required
                       class="mt-2 w-full border border-ink/20 bg-transparent px-3 py-2 font-sans">
            </div>

            @if ($errors->any())
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li class="font-sans text-xs text-accent">{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            <button type="submit"
                    class="block w-full bg-ink px-6 py-4 text-center font-serif text-sm tracking-widest text-ground hover:bg-accent">
                {{ __('shop.orders.find_submit') }}
            </button>
        </form>
    </div>
</x-layouts.storefront>
