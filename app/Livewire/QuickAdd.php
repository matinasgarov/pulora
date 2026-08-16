<?php // app/Livewire/QuickAdd.php

namespace App\Livewire;

use App\Domain\Cart\CartService;
use App\Domain\Catalog\Models\Product;
use Livewire\Component;

/**
 * The collection tile's "add to bag" overlay. Only ever mounted when
 * Product::canQuickAdd() is true — see resources/views/components/
 * product-tile.blade.php — so there is exactly one active variant and no
 * required personalization left unanswered by a single click.
 */
class QuickAdd extends Component
{
    public Product $product;

    public function add(CartService $cart): void
    {
        $variant = $this->product->variants->firstWhere('is_active', true);

        if (! $variant) {
            return;
        }

        $cart->add($variant->id, 1);

        $this->dispatch('cart-updated');
    }

    public function render()
    {
        return view('livewire.quick-add');
    }
}
