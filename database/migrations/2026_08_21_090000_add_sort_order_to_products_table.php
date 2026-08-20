<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The grid's default sort was the products table's id order, which is the
 * order rows happened to be inserted. That held only for a database seeded
 * once from top to bottom: every batch of photographs added since landed at
 * the end, so four new card holders sat behind the document holders instead of
 * beside the other card cases — while WalletCatalogue.php, which is hand
 * ordered precisely so colourways of one piece sit together, had no say in it.
 *
 * The default is 1000, not 0, so a product the catalogue does not know about
 * sorts after the ones it does rather than jumping to the front of the shop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->unsignedSmallInteger('sort_order')->default(1000)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->dropColumn('sort_order');
        });
    }
};
