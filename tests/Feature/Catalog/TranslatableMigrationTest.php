<?php // tests/Feature/Catalog/TranslatableMigrationTest.php

use App\Domain\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * The migration that made catalogue content translatable is reversible in both
 * directions, and rollback is the path that can destroy content the operator
 * typed. It had no committed coverage — it was verified once by hand against a
 * throwaway database — so these tests drive the migration object directly.
 *
 * up() and down() only rewrite data (the column widening is idempotent), which
 * is what makes it safe to run them against the already-migrated test schema.
 */
function catalogMigration(): object
{
    return require database_path('migrations/2026_08_14_000100_make_catalog_content_translatable.php');
}

function rawName(int $id): ?string
{
    return DB::table('products')->where('id', $id)->value('name');
}

it('unwraps per-locale content back to the fallback locale', function () {
    $product = Product::factory()->create();

    DB::table('products')->where('id', $product->id)->update([
        'name' => json_encode(['en' => 'Bifold wallet', 'az' => 'İkiqat pulqabı'], JSON_UNESCAPED_UNICODE),
    ]);

    catalogMigration()->down();

    expect(rawName($product->id))->toBe('Bifold wallet');
});

it('rewraps a bare string on the way back up', function () {
    $product = Product::factory()->create();

    DB::table('products')->where('id', $product->id)->update(['name' => 'Bifold wallet']);

    catalogMigration()->up();

    expect(rawName($product->id))->toBe('{"en":"Bifold wallet"}');
});

it('survives a full down-and-up round trip without changing the content', function () {
    $product = Product::factory()->create();

    DB::table('products')->where('id', $product->id)->update([
        'name' => json_encode(['en' => 'Bifold wallet'], JSON_UNESCAPED_UNICODE),
    ]);

    $migration = catalogMigration();
    $migration->down();
    $migration->up();

    expect(rawName($product->id))->toBe('{"en":"Bifold wallet"}');
});

it('leaves already-wrapped content alone when up() runs twice', function () {
    $product = Product::factory()->create();

    DB::table('products')->where('id', $product->id)->update([
        'name' => json_encode(['en' => 'Bifold wallet'], JSON_UNESCAPED_UNICODE),
    ]);

    catalogMigration()->up();

    expect(rawName($product->id))->toBe('{"en":"Bifold wallet"}');
});

// `?:` treats the string "0" as falsy, so the obvious one-liner form of the
// unwrap silently emptied any field whose content was literally "0".
it('preserves a value of "0" through a rollback', function () {
    $product = Product::factory()->create();

    DB::table('products')->where('id', $product->id)->update([
        'name' => json_encode(['en' => '0'], JSON_UNESCAPED_UNICODE),
    ]);

    catalogMigration()->down();

    expect(rawName($product->id))->toBe('0');
});

it('falls back to another locale when the default one is empty', function () {
    $product = Product::factory()->create();

    DB::table('products')->where('id', $product->id)->update([
        'name' => json_encode(['en' => '', 'az' => 'İkiqat pulqabı'], JSON_UNESCAPED_UNICODE),
    ]);

    catalogMigration()->down();

    expect(rawName($product->id))->toBe('İkiqat pulqabı');
});
