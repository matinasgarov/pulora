<?php // app/Domain/Catalog/Models/Variant.php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Variant extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'price_minor_override' => 'integer',
        'stock_quantity' => 'integer',
        'weight_grams' => 'integer',
        'is_active' => 'boolean',
    ];

    public function product() { return $this->belongsTo(Product::class); }

    public function effectivePriceMinor(): int
    {
        return $this->price_minor_override ?? $this->product->base_price_minor;
    }

    protected static function newFactory()
    {
        return \Database\Factories\VariantFactory::new();
    }
}
