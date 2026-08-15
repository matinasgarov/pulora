<?php // app/Livewire/CartCount.php

namespace App\Livewire;

use App\Domain\Cart\CartService;
use Livewire\Attributes\On;
use Livewire\Component;

class CartCount extends Component
{
    public function render(CartService $cart)
    {
        return view('livewire.cart-count', [
            'count' => $cart->snapshot()->totalQuantity(),
        ]);
    }

    /** Dispatched by ProductPurchase after a successful add. */
    #[On('cart-updated')]
    public function refreshCount(): void
    {
        // Re-rendering is the whole job; the render method reads the session.
    }
}
