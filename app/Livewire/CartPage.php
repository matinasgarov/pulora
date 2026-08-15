<?php // app/Livewire/CartPage.php

namespace App\Livewire;

use App\Domain\Cart\CartService;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.storefront')]
class CartPage extends Component
{
    public function mount(): void
    {
        // Normally set by the `setlocale` route middleware before this
        // component boots. Livewire component tests instantiate the
        // component directly, bypassing that middleware, so the checkout
        // link's route('storefront.checkout') would have no {locale} to
        // fill in. Setting it here is idempotent in production — the
        // middleware has already set it to the same value.
        URL::defaults(['locale' => app()->getLocale()]);
    }

    public function remove(CartService $cart, string $lineKey): void
    {
        $cart->remove($lineKey);

        $this->dispatch('cart-updated');
    }

    public function render(CartService $cart)
    {
        $snapshot = $cart->snapshot();

        // snapshot() silently drops lines whose variant or product was
        // deactivated. Watching an item vanish with no explanation is worse
        // than the retirement itself, so say so.
        $rawLineCount = count(session('cart.lines', []));
        $dropped = $rawLineCount > count($snapshot->lines);

        return view('livewire.cart-page', [
            'lines' => $snapshot->lines,
            'subtotalMinor' => $snapshot->subtotalMinor(),
            'dropped' => $dropped,
        ]);
    }
}
