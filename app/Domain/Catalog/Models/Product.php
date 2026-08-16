<?php // app/Domain/Catalog/Models/Product.php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\ProductCategory;
use App\Domain\Catalog\ProductTag;
use App\Support\HasTranslations;
use App\Support\TranslatableListCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $guarded = [];

    /**
     * `specs` is deliberately absent here — it is a list, not a scalar
     * string, and HasTranslations::resolveTranslation() is typed to return
     * string. It gets its own resolution via TranslatableListCast below
     * instead of bending that trait's return type. See that cast's docblock.
     */
    protected array $translatable = ['name', 'description', 'story', 'leather'];

    protected $casts = [
        'base_price_minor' => 'integer',
        'is_active' => 'boolean',
        'category' => ProductCategory::class,
        'tag' => ProductTag::class,
        'specs' => TranslatableListCast::class,
    ];

    public function variants() { return $this->hasMany(Variant::class); }
    public function images() { return $this->hasMany(ProductImage::class)->orderBy('sort_order'); }
    public function personalizationOptions() { return $this->hasMany(PersonalizationOption::class); }
    public function variantOptions() { return $this->hasMany(VariantOption::class)->orderBy('sort_order'); }

    public function scopeActive(Builder $q): Builder { return $q->where('is_active', true); }

    /**
     * A one-click "add to bag" from the collection grid can only answer a
     * single question ("add this"), not "which colour" or "what monogram".
     * It is only safe when there is exactly one active variant to add and no
     * required personalization option waiting for an answer the tile cannot
     * collect. Assumes `variants` and `personalizationOptions` are already
     * eager-loaded — this filters in memory rather than issuing a query per
     * tile.
     */
    public function canQuickAdd(): bool
    {
        return $this->variants->where('is_active', true)->count() === 1
            && $this->personalizationOptions->where('is_required', true)->isEmpty();
    }

    /**
     * The raw per-locale map for `specs` ({"en": [...], "az": [...]}), for
     * admin editing where every locale must be visible at once. Unlike
     * HasTranslations::getTranslations(), this cannot read the attribute
     * through the model — `specs`'s cast (TranslatableListCast) already
     * resolves it down to the active locale's list — so it decodes the raw
     * database value directly instead.
     */
    public function getSpecsTranslations(): array
    {
        $raw = $this->getRawOriginal('specs');
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($decoded) ? $decoded : [];
    }

    protected static function newFactory()
    {
        return \Database\Factories\ProductFactory::new();
    }
}
