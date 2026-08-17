<?php // app/Livewire/CartPage.php

namespace App\Livewire;

use App\Domain\Cart\CartService;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.storefront')]
class CartPage extends Component
{
    /**
     * boot(), not mount().
     *
     * The `setlocale` route middleware sets this default for ordinary page
     * requests, but a Livewire action posts to /livewire/update, which never
     * passes through the storefront's locale-prefixed routes. mount() does not
     * run on those requests either — so after any action, the re-render hit
     * route('storefront.checkout') with no {locale} to fill in and threw.
     *
     * Remove had this fault from the start; it took adding a second action to
     * make anyone press a button on this page twice and see it. boot() runs on
     * every request to the component, initial and subsequent alike.
     */
    public function boot(): void
    {
        URL::defaults(['locale' => app()->getLocale()]);
    }

    public function remove(CartService $cart, string $lineKey): void
    {
        $cart->remove($lineKey);

        $this->dispatch('cart-updated');
    }

    /**
     * Move a line by one, in either direction.
     *
     * The quantity is read from the cart rather than passed up from the page,
     * so two quick clicks cannot both act on the same stale number and lose
     * one of the steps. Stepping below one removes the line — see
     * CartService::setQuantity().
     */
    public function step(CartService $cart, string $lineKey, int $by): void
    {
        $line = collect($cart->snapshot()->lines)->firstWhere('lineKey', $lineKey);

        if ($line === null) {
            return;
        }

        $cart->setQuantity($lineKey, $line->quantity + $by);

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
