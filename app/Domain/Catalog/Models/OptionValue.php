<?php // app/Domain/Catalog/Models/OptionValue.php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

class OptionValue extends Model
{
    protected $guarded = [];

    public function option() { return $this->belongsTo(VariantOption::class, 'variant_option_id'); }
    public function variants() { return $this->belongsToMany(Variant::class); }
}
