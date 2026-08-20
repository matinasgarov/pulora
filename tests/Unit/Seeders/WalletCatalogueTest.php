<?php // tests/Unit/Seeders/WalletCatalogueTest.php

use App\Domain\Catalog\ProductCategory;
use Database\Seeders\WalletCatalogue;
use Database\Seeders\WalletImagesSeeder;

it('tells a product photograph from anything else dropped in the folder', function () {
    foreach (['a1', 'k3', 'a_1', 'd_2', 'y10', 'a__1', 'd__2'] as $ok) {
        expect(WalletImagesSeeder::isProductPhoto($ok))->toBeTrue($ok);
    }

    // hero.png joined the H wallet once, and a stray "ChatGPT Image…" joined C.
    // "h4 (2)" is the same shot twice.
    foreach (['hero', 'ChatGPT Image Aug 17', 'Gemini_Generated_Image_98vm', 'h4 (2)', 'ab1', '1a'] as $no) {
        expect(WalletImagesSeeder::isProductPhoto($no))->toBeFalse($no);
    }
});

it('keeps every underscore, so a1, a_1 and a__1 stay three products', function () {
    // a1 is the teal card case, a_1 the walnut dopp kit, a__1 the signature
    // card holder. Each batch of photographs has arrived under the same
    // letters with one more underscore, so the count is not fixed at one:
    // trimming them, or allowing only a single one, folds a whole batch into
    // the batch before it.
    expect(WalletImagesSeeder::prefix('a1'))->toBe('a')
        ->and(WalletImagesSeeder::prefix('a_1'))->toBe('a_')
        ->and(WalletImagesSeeder::prefix('d_12'))->toBe('d_')
        ->and(WalletImagesSeeder::prefix('a__1'))->toBe('a__')
        ->and(WalletImagesSeeder::prefix('d__2'))->toBe('d__');
});


it('gives every group a name in both languages', function () {
    foreach (WalletCatalogue::all() as $prefix => $d) {
        expect($d['name']['en'])->not->toBe('')
            ->and($d['name']['az'])->not->toBe('')
            ->and($d['name']['az'])->not->toBe($d['name']['en'], "$prefix is not actually translated")
            ->and($d['description']['en'])->not->toBe('')
            ->and($d['description']['az'])->not->toBe($d['description']['en'], "$prefix description is not translated")
            ->and($d['leather']['az'])->not->toBe('')
            ->and($d['category'])->toBeInstanceOf(ProductCategory::class)
            ->and($d['price'])->toBeGreaterThan(0);
    }
});

it('gives every group a distinct name and slug', function () {
    $names = array_map(fn ($d) => $d['name']['en'], WalletCatalogue::all());

    expect($names)->toHaveCount(count(array_unique($names)));
    expect(array_map(fn ($n) => \Illuminate\Support\Str::slug($n), $names))
        ->toHaveCount(count(array_unique(array_map(fn ($n) => \Illuminate\Support\Str::slug($n), $names))));
});

it('puts the same piece in different colours next to each other', function () {
    // The grid's default sort is insertion order, and the seeder inserts in
    // this order — so a family split across the list would scatter its colours
    // across the grid, which is the thing this ordering exists to prevent.
    $families = [];

    foreach (array_values(array_map(fn ($d) => $d['name']['en'], WalletCatalogue::all())) as $position => $name) {
        // "Presidential Bifold — Cognac" -> "Presidential Bifold"
        $families[trim(explode('—', $name)[0])][] = $position;
    }

    foreach ($families as $family => $positions) {
        expect(max($positions) - min($positions))->toBe(count($positions) - 1,
            "$family is split up in the grid order");
    }
});
