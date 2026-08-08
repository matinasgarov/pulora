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
        $variant = Variant::with('product.personalizationOptions')->findOrFail($variantId);
        $clean = $this->validator->validate($variant->product, $personalization);

        $lineKey = $this->lineKey($variantId, $clean);
        $lines = $this->rawLines();

        $lines[$lineKey] = [
            'variant_id' => $variantId,
            'quantity' => ($lines[$lineKey]['quantity'] ?? 0) + max(1, $quantity),
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

        $variants = Variant::with('product.personalizationOptions')
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
