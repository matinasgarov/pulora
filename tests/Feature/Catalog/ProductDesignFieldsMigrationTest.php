<?php // tests/Feature/Catalog/ProductDesignFieldsMigrationTest.php

use App\Domain\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026_08_16_000100_add_design_fields_to_products.php adds four columns and
 * nothing else — it doesn't rewrite data the way the translatable-content
 * migration does — so a reversibility test only needs to prove down()/up()
 * add and remove the columns cleanly, following TranslatableMigrationTest's
 * pattern of driving the migration object directly.
 */
function designFieldsMigration(): object
{
    return require database_path('migrations/2026_08_16_000100_add_design_fields_to_products.php');
}

it('adds the four design columns to products', function () {
    expect(Schema::hasColumns('products', ['leather', 'category', 'tag', 'specs']))->toBeTrue();
});

it('drops the design columns on down() and restores them on up()', function () {
    $migration = designFieldsMigration();

    $migration->down();
    expect(Schema::hasColumns('products', ['leather', 'category', 'tag', 'specs']))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumns('products', ['leather', 'category', 'tag', 'specs']))->toBeTrue();
});

it('loses column content on a rollback, same as any dropped column, and a fresh column is empty after up() again', function () {
    $product = Product::factory()->create([
        'leather' => ['en' => 'Vegetable-tanned · Natural'],
        'category' => 'wallet',
        'tag' => 'new',
    ]);

    $migration = designFieldsMigration();
    $migration->down();
    $migration->up();

    expect(DB::table('products')->where('id', $product->id)->first())
        ->leather->toBeNull()
        ->category->toBeNull()
        ->tag->toBeNull()
        ->specs->toBeNull();
});
