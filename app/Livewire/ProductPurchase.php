<?php // app/Livewire/ProductPurchase.php

namespace App\Livewire;

use App\Domain\Cart\CartService;
use App\Domain\Cart\InvalidPersonalizationException;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use Livewire\Component;

class ProductPurchase extends Component
{
    public Product $product;

    public ?int $variantId = null;

    /** type => value, e.g. ['monogram' => 'MA'] */
    public array $personalization = [];

    public function mount(Product $product): void
    {
        $this->product = $product;

        $this->variantId = $product->variants
                ->first(fn (Variant $v) => $v->is_active && $v->stock_quantity > 0)?->id
            ?? $product->variants->firstWhere('is_active', true)?->id;
    }

    public function getVariantProperty(): ?Variant
    {
        return $this->product->variants->firstWhere('id', $this->variantId);
    }

    /** Capacity is an operator-set cap; at zero the piece cannot be committed to. */
    public function getAvailableProperty(): bool
    {
        return $this->variant !== null && $this->variant->stock_quantity > 0;
    }

    /**
     * Mirrors CartService::snapshot() exactly: effective price plus the delta of
     * every selected personalization option. The cart must never disagree with
     * what the customer was shown here.
     */
    public function getUnitPriceMinorProperty(): int
    {
        if (! $this->variant) {
            return 0;
        }

        $delta = $this->product->personalizationOptions
            ->whereIn('type', array_keys(array_filter($this->personalization)))
            ->sum('price_delta_minor');

        return $this->variant->effectivePriceMinor() + $delta;
    }

    public function add(CartService $cart): void
    {
        if (! $this->available) {
            return;
        }

        try {
            // CartService validates personalization against the product's own
            // options. The storefront does not restate those rules.
            $cart->add($this->variantId, 1, array_filter($this->personalization));
        } catch (InvalidPersonalizationException $e) {
            // The exception names the option it is about, so the error lands on
            // the input the customer actually got wrong. Falling back to the
            // group keeps a message on screen rather than a dead "Add to bag".
            $this->addError(
                $e->type ? "personalization.{$e->type}" : 'personalization',
                $e->getMessage(),
            );

            return;
        }

        $this->dispatch('cart-updated');
    }

    public function render()
    {
        return view('livewire.product-purchase');
    }
}
