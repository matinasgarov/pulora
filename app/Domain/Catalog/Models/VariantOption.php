<?php // app/Domain/Catalog/Models/VariantOption.php

namespace App\Domain\Catalog\Models;

use App\Support\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class VariantOption extends Model
{
    use HasTranslations;

    protected $guarded = [];

    protected array $translatable = ['name'];

    public function product() { return $this->belongsTo(Product::class); }
    public function values() { return $this->hasMany(OptionValue::class)->orderBy('sort_order'); }
}
