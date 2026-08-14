<?php // app/Domain/Catalog/Models/ProductImage.php

namespace App\Domain\Catalog\Models;

use App\Support\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasTranslations;

    protected $guarded = [];

    protected array $translatable = ['alt_text'];
}
