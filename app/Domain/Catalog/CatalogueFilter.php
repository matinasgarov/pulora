<?php // app/Domain/Catalog/CatalogueFilter.php

namespace App\Domain\Catalog;

use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The catalogue's search, filter and sort state, read from the query string.
 *
 * State lives in the URL rather than in a Livewire component, so a filtered
 * catalogue can be linked, shared, bookmarked and reached with the back button,
 * and so the whole thing works with scripting off. The header's search field
 * and the filter panel are both plain GET forms pointing here.
 *
 * Filtering, sorting and paging all happen in the database. They used to run in
 * PHP over the whole active catalogue, which was fine while the shop held
 * fifteen pieces and stopped being fine the moment it did not: every request
 * loaded every product, with its images, variants and personalization options,
 * to show twelve of them.
 *
 * What made that hard to move into SQL was search. `name`, `leather` and
 * `description` are translatable JSON whose accessors resolve a locale and fall
 * back, and the search folds Azerbaijani diacritics so "cuzdan" finds "cüzdan".
 * A LIKE over the raw JSON matches the locale keys themselves and folds
 * nothing. The answer is to fold once on write instead of every time on read:
 * Product::syncSearchText() maintains a `search_text` column holding exactly
 * the string a reader of each locale would see, already folded, so searching is
 * an ordinary LIKE on one key of it.
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
     * Narrows and orders the catalogue query.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function apply(Builder $query): Builder
    {
        return $this->sortQuery(
            $query
                ->when($this->category !== null, fn (Builder $q) => $q->where('category', $this->category))
                ->when($this->price !== null, function (Builder $q) {
                    [$min, $max] = self::PRICE_BANDS[$this->price];

                    $q->where('base_price_minor', '>=', $min)
                        ->when($max !== null, fn (Builder $q) => $q->where('base_price_minor', '<', $max));
                })
                ->when($this->q !== null, fn (Builder $q) => $this->search($q))
        );
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function search(Builder $query): Builder
    {
        $needle = self::fold($this->q);

        if ($needle === '') {
            return $query;
        }

        // The column is stored per locale and already folded, so the needle
        // only has to be folded to match it.
        $column = 'search_text->'.app()->getLocale();

        // Every word must appear, so "black card" narrows rather than widening
        // to everything black plus everything with a card slot.
        foreach (preg_split('/\s+/', $needle) as $word) {
            if ($word === '') {
                continue;
            }

            $query->where($column, 'like', '%'.self::escapeLike($word).'%');
        }

        return $query;
    }

    /** So a customer searching for "50%" does not match everything. */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
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
     * Every order ends with `id` so that ties resolve the same way twice.
     * Without it two products at the same price can swap places between page 1
     * and page 2 and one of them is never shown at all.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function sortQuery(Builder $query): Builder
    {
        return match ($this->sort) {
            'price_asc' => $query->orderBy('base_price_minor')->orderBy('id'),
            'price_desc' => $query->orderByDesc('base_price_minor')->orderBy('id'),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            // "Featured" is the operator's own order, which is insertion order.
            default => $query->orderBy('id'),
        };
    }
}
