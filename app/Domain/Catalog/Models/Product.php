<?php // app/Domain/Catalog/Models/Product.php

namespace App\Domain\Catalog\Models;

use App\Support\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $guarded = [];

    protected array $translatable = ['name', 'description', 'story'];

    protected $casts = [
        'base_price_minor' => 'integer',
        'is_active' => 'boolean',
    ];

    public function variants() { return $this->hasMany(Variant::class); }
    public function images() { return $this->hasMany(ProductImage::class)->orderBy('sort_order'); }
    public function personalizationOptions() { return $this->hasMany(PersonalizationOption::class); }
    public function variantOptions() { return $this->hasMany(VariantOption::class)->orderBy('sort_order'); }

    public function scopeActive(Builder $q): Builder { return $q->where('is_active', true); }

    protected static function newFactory()
    {
        return \Database\Factories\ProductFactory::new();
    }
}
