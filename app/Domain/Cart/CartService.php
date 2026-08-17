<?php // app/Domain/Cart/CartService.php

namespace App\Domain\Cart;

use App\Domain\Catalog\Models\Variant;
use Illuminate\Contracts\Session\Session;

class CartService
{
    private const KEY = 'cart.lines';

    public function __construct(
        private Session $session,
        private PersonalizationValidator $validator,
    ) {}

    public function add(int $variantId, int $quantity, array $personalization = []): void
    {
        if ($quantity < 1) {
            throw new InvalidQuantityException('Quantity must be at least 1.');
        }

        $variant = Variant::with('product.personalizationOptions')->findOrFail($variantId);
        $clean = $this->validator->validate($variant->product, $personalization);

        $lineKey = $this->lineKey($variantId, $clean);
        $lines = $this->rawLines();

        $lines[$lineKey] = [
            'variant_id' => $variantId,
            'quantity' => ($lines[$lineKey]['quantity'] ?? 0) + $quantity,
            'personalization' => $clean,
        ];

        $this->session->put(self::KEY, $lines);
    }

    public function remove(string $lineKey): void
    {
        $lines = $this->rawLines();
        unset($lines[$lineKey]);
        $this->session->put(self::KEY, $lines);
    }

    /**
     * Set a line to an exact quantity.
     *
     * Dropping to zero removes the line rather than storing an empty one: a
     * quantity control that can reach zero is the same gesture as removing, and
     * a line of nothing is not a thing snapshot() should have to reason about.
     * Unknown keys are ignored, because the line may have been retired between
     * the page rendering and the click.
     */
    public function setQuantity(string $lineKey, int $quantity): void
    {
        $lines = $this->rawLines();

        if (! isset($lines[$lineKey])) {
            return;
        }

        if ($quantity < 1) {
            $this->remove($lineKey);

            return;
        }

        $lines[$lineKey]['quantity'] = $quantity;

        $this->session->put(self::KEY, $lines);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    public function snapshot(): CartSnapshot
    {
        $raw = $this->rawLines();
        if ($raw === []) {
            return new CartSnapshot([]);
        }

        $variants = Variant::with([
            'product.personalizationOptions',
            // Ordered, not limited: a `limit` inside an eager load applies to
            // the whole query rather than per parent unless it is written as a
            // one-of-many relation, and a bag holds a handful of lines.
            'product.images' => fn ($q) => $q->orderBy('sort_order'),
        ])
            ->whereIn('id', array_column($raw, 'variant_id'))
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $lines = [];

        foreach ($raw as $key => $item) {
            $variant = $variants->get($item['variant_id']);
            if (! $variant || ! $variant->product->is_active) {
                continue;
            }

            $lines[] = new CartLine(
                lineKey: $key,
                variantId: $variant->id,
                quantity: $item['quantity'],
                productName: $variant->product->name,
                variantDescription: $variant->description,
                unitPriceMinor: $variant->effectivePriceMinor()
                    + $this->personalizationDeltaMinor($variant, $item['personalization']),
                personalization: $item['personalization'],
                weightGrams: $variant->weight_grams,
                imagePath: $variant->product->images->first()?->path,
            );
        }

        return new CartSnapshot($lines);
    }

    private function personalizationDeltaMinor(Variant $variant, array $personalization): int
    {
        return $variant->product->personalizationOptions
            ->whereIn('type', array_keys($personalization))
            ->sum('price_delta_minor');
    }

    private function lineKey(int $variantId, array $personalization): string
    {
        ksort($personalization);

        return $variantId . ':' . md5(json_encode($personalization));
    }

    private function rawLines(): array
    {
        return $this->session->get(self::KEY, []);
    }
}
