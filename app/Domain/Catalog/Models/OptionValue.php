<?php // app/Domain/Catalog/Models/OptionValue.php

namespace App\Domain\Catalog\Models;

use App\Support\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class OptionValue extends Model
{
    use HasTranslations;

    protected $guarded = [];

    protected array $translatable = ['value'];

    public function option() { return $this->belongsTo(VariantOption::class, 'variant_option_id'); }
    public function variants() { return $this->belongsToMany(Variant::class); }
}
