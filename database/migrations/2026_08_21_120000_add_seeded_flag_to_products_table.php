<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a product as owned by WalletImagesSeeder, so a rename in
 * WalletCatalogue.php can be pruned safely.
 *
 * Renaming a product changes its slug, and Product::firstOrNew() keys on
 * slug — so a rename does not update the existing row, it inserts a second
 * one under the new slug and leaves the old one behind, active, in the shop,
 * still holding the old name and the same photographs. This has already
 * happened once: renaming "Presidential" to "The President" produced five
 * such pairs.
 *
 * The seeder already deleted stale rows for one historical case
 * (`card-holder-%`, from an earlier naming scheme), matched by a slug
 * pattern. That does not generalise — the next rename needs its own pattern,
 * forever. A boolean instead: only a row this seeder created is eligible to
 * be pruned when its slug falls out of the current list, so a product added
 * by hand in the admin panel — which never sets this flag — can never be
 * touched by it, no matter what its slug happens to look like.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->boolean('seeded')->default(false)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->dropColumn('seeded');
        });
    }
};
