<?php // tests/Unit/Seeders/WalletCatalogueTest.php

use App\Domain\Catalog\ProductCategory;
use Database\Seeders\WalletCatalogue;
use Database\Seeders\WalletImagesSeeder;

it('tells a product photograph from anything else dropped in the folder', function () {
    foreach (['a1', 'k3', 'a_1', 'd_2', 'y10'] as $ok) {
        expect(WalletImagesSeeder::isProductPhoto($ok))->toBeTrue($ok);
    }

    // hero.png joined the H wallet once, and a stray "ChatGPT Image…" joined C.
    // "h4 (2)" is the same shot twice.
    foreach (['hero', 'ChatGPT Image Aug 17', 'Gemini_Generated_Image_98vm', 'h4 (2)', 'ab1', '1a'] as $no) {
        expect(WalletImagesSeeder::isProductPhoto($no))->toBeFalse($no);
    }
});

it('keeps the underscore, so a1 and a_1 stay two different products', function () {
    // a1 is the teal card case; a_1 is the walnut dopp kit. Trimming the
    // underscore as though it were a separator would merge them.
    expect(WalletImagesSeeder::prefix('a1'))->toBe('a')
        ->and(WalletImagesSeeder::prefix('a_1'))->toBe('a_')
        ->and(WalletImagesSeeder::prefix('d_12'))->toBe('d_');
});

it('gives every group a name in both languages', function () {
    foreach (WalletCatalogue::all() as $prefix => [$en, $az, $category, $price, $leatherEn, $leatherAz]) {
        expect($en)->not->toBe('')->and($az)->not->toBe('')
            ->and($az)->not->toBe($en, "$prefix is not actually translated")
            ->and($category)->toBeInstanceOf(ProductCategory::class)
            ->and($price)->toBeGreaterThan(0);
    }
});

it('gives every group a distinct name and slug', function () {
    $names = array_column(WalletCatalogue::all(), 0);

    expect($names)->toHaveCount(count(array_unique($names)));
    expect(array_map(fn ($n) => \Illuminate\Support\Str::slug($n), $names))
        ->toHaveCount(count(array_unique(array_map(fn ($n) => \Illuminate\Support\Str::slug($n), $names))));
});

it('puts the same piece in different colours next to each other', function () {
    // The grid's default sort is insertion order, and the seeder inserts in
    // this order — so a family split across the list would scatter its colours
    // across the grid, which is the thing this ordering exists to prevent.
    $families = [];

    foreach (array_column(WalletCatalogue::all(), 0) as $position => $name) {
        // "Presidential Bifold — Cognac" -> "Presidential Bifold"
        $families[trim(explode('—', $name)[0])][] = $position;
    }

    foreach ($families as $family => $positions) {
        expect(max($positions) - min($positions))->toBe(count($positions) - 1,
            "$family is split up in the grid order");
    }
});
