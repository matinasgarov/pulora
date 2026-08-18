<?php

use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A folded, per-locale copy of the text the catalogue searches.
 *
 * Search reads `name`, `leather` and `description` — all translatable JSON —
 * lowercases them and strips Azerbaijani diacritics, so that someone typing
 * "cuzdan" finds "cüzdan". None of that survives a translation into SQL: a
 * LIKE over the raw JSON matches the locale keys themselves and cannot fold
 * anything, and JSON path queries miss rows still stored as plain strings.
 *
 * So the folding happens once, on write, into a column shaped the same way as
 * the columns it summarises: {"en": "...", "az": "..."}, each already resolved
 * through the same locale fallback a reader would get. Searching is then an
 * ordinary LIKE on one locale's key, which is what lets filtering, sorting and
 * paging all happen in the database instead of over the whole table in PHP.
 *
 * Product::syncSearchText() owns the contents; this only adds the column and
 * fills it for rows that already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('search_text')->nullable()->after('leather');
        });

        Product::query()->withoutGlobalScopes()->cursor()
            ->each(fn (Product $product) => $product->syncSearchText()->saveQuietly());
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('search_text');
        });
    }
};
