<?php // database/migrations/2026_08_16_000100_add_design_fields_to_products.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The four fields the Pulora design needs that the catalogue never carried:
 * `leather` (translatable label), `category` (wallet|card, backed by
 * ProductCategory), `tag` (new|low_stock, operator-set, backed by
 * ProductTag), and `specs` (translatable ordered list of label/value pairs).
 *
 * `leather` and `specs` are stored as `text`, the same column type
 * 2026_08_14_000100_make_catalog_content_translatable.php widened `name` and
 * `description` to — two locales of JSON will not fit in a `string(255)`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->text('leather')->nullable()->after('story');
            $t->string('category')->nullable()->after('leather');
            $t->string('tag')->nullable()->after('category');
            $t->text('specs')->nullable()->after('tag');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->dropColumn(['leather', 'category', 'tag', 'specs']);
        });
    }
};
