<?php // app/Domain/Catalog/Models/VariantOption.php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

class VariantOption extends Model
{
    protected $guarded = [];

    public function product() { return $this->belongsTo(Product::class); }
    public function values() { return $this->hasMany(OptionValue::class)->orderBy('sort_order'); }
}
