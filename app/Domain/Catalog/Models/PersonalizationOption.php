<?php // app/Domain/Catalog/Models/PersonalizationOption.php

namespace App\Domain\Catalog\Models;

use App\Support\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class PersonalizationOption extends Model
{
    use HasTranslations;

    protected $guarded = [];

    protected array $translatable = ['label'];

    protected $casts = [
        'price_delta_minor' => 'integer',
        'max_characters' => 'integer',
        'is_required' => 'boolean',
    ];

    public function product() { return $this->belongsTo(Product::class); }
}
