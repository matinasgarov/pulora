<?php // app/Domain/Catalog/CatalogueFilter.php

namespace App\Domain\Catalog;

use App\Domain\Catalog\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The catalogue's search, filter and sort state, read from the query string.
 *
 * State lives in the URL rather than in a Livewire component, so a filtered
 * catalogue can be linked, shared, bookmarked and reached with the back button,
 * and so the whole thing works with scripting off. The header's search field
 * and the filter panel are both plain GET forms pointing here.
 *
 * Filtering happens in memory over the already-loaded catalogue rather than in
 * SQL. Two reasons: the controller loads every active product anyway (the grid
 * shows them all), so this adds no queries; and `name` and `leather` are
 * translatable JSON columns whose accessors resolve a locale and fall back —
 * matching that in SQL means either JSON path queries that miss rows stored as
 * plain strings, or a LIKE across raw JSON that matches the locale keys
 * themselves. Reading the resolved values is simply correct. If this catalogue
 * ever grows past a few hundred pieces, that trade flips and this belongs in
 * the query.
 */
final readonly class CatalogueFilter
{
    public const SORTS = ['featured', 'price_asc', 'price_desc', 'newest'];

    /** Bands in minor units (qəpik): [inclusive min, exclusive max|null]. */
    public const PRICE_BANDS = [
        'under_50' => [0, 5000],
        '50_100' => [5000, 10000],
        'over_100' => [10000, null],
    ];

    public function __construct(
        public ?string $q = null,
        public ?string $category = null,
        public ?string $price = null,
        public string $sort = 'featured',
    ) {}

    public static function fromRequest(Request $request): self
    {
        $q = trim((string) $request->query('q', ''));
        $category = (string) $request->query('category', '');
        $price = (string) $request->query('price', '');
        $sort = (string) $request->query('sort', '');

        return new self(
            q: $q === '' ? null : $q,
            // Anything unrecognised is dropped rather than honoured, so a
            // hand-edited URL cannot produce an empty grid with no explanation.
            category: ProductCategory::tryFrom($category)?->value,
            price: isset(self::PRICE_BANDS[$price]) ? $price : null,
            sort: in_array($sort, self::SORTS, true) ? $sort : 'featured',
        );
    }

    public function isActive(): bool
    {
        return $this->q !== null || $this->category !== null || $this->price !== null || $this->sort !== 'featured';
    }

    /** The query string for this state, with any part replaced. */
    public function toQuery(array $overrides = []): array
    {
        return array_filter([
            'q' => $this->q,
            'category' => $this->category,
            'price' => $this->price,
            'sort' => $this->sort === 'featured' ? null : $this->sort,
            ...$overrides,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    public function apply(Collection $products): Collection
    {
        return $this->sortProducts(
            $products
                ->when($this->category !== null, fn ($c) => $c->filter(
                    fn (Product $p) => $p->category?->value === $this->category
                ))
                ->when($this->price !== null, fn ($c) => $c->filter(
                    fn (Product $p) => $this->inPriceBand($p->base_price_minor)
                ))
                ->when($this->q !== null, fn ($c) => $c->filter(
                    fn (Product $p) => $this->matches($p)
                ))
        )->values();
    }

    private function inPriceBand(int $priceMinor): bool
    {
        [$min, $max] = self::PRICE_BANDS[$this->price];

        return $priceMinor >= $min && ($max === null || $priceMinor < $max);
    }

    private function matches(Product $product): bool
    {
        $needle = self::fold($this->q);

        if ($needle === '') {
            return true;
        }

        $haystack = self::fold(implode(' ', array_filter([
            $product->name,
            $product->leather,
            $product->description,
        ])));

        // Every word must appear, so "black card" narrows rather than widening
        // to everything black plus everything with a card slot.
        foreach (preg_split('/\s+/', $needle) as $word) {
            if ($word !== '' && ! str_contains($haystack, $word)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Lowercase and strip Azerbaijani diacritics.
     *
     * Someone typing "cuzdan" on a keyboard without ü means "cüzdan", and a
     * search that makes them find the right key first is a search that does not
     * work. Folding both sides of the comparison costs nothing here.
     */
    public static function fold(string $value): string
    {
        return strtr(mb_strtolower($value, 'UTF-8'), [
            'ə' => 'e', 'ö' => 'o', 'ü' => 'u', 'ç' => 'c',
            'ğ' => 'g', 'ş' => 's', 'ı' => 'i', 'i̇' => 'i',
        ]);
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    private function sortProducts(Collection $products): Collection
    {
        return match ($this->sort) {
            'price_asc' => $products->sortBy->base_price_minor,
            'price_desc' => $products->sortByDesc->base_price_minor,
            'newest' => $products->sortByDesc->created_at,
            // "Featured" is the operator's own order, which is the order the
            // controller already fetched them in.
            default => $products,
        };
    }
}
