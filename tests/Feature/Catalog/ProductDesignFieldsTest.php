<?php // tests/Feature/Catalog/ProductDesignFieldsTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\ProductCategory;
use App\Domain\Catalog\ProductTag;
use Illuminate\Support\Facades\DB;

it('resolves leather per locale', function () {
    $product = Product::factory()->create([
        'leather' => ['en' => 'Vegetable-tanned · Natural', 'az' => 'Bitkisel aşılanmış · Natural'],
    ]);

    app()->setLocale('az');
    expect(Product::find($product->id)->leather)->toBe('Bitkisel aşılanmış · Natural');

    app()->setLocale('en');
    expect(Product::find($product->id)->leather)->toBe('Vegetable-tanned · Natural');
});

it('falls back to the fallback locale when leather is missing for the active one', function () {
    $product = Product::factory()->create([
        'leather' => ['en' => 'Vegetable-tanned · Natural'],
    ]);

    app()->setLocale('az');

    expect(Product::find($product->id)->leather)->toBe('Vegetable-tanned · Natural');
});

it('is nullable', function () {
    $product = Product::factory()->create(['leather' => null]);

    expect(Product::find($product->id)->leather)->toBe('');
});

it('round-trips specs as an ordered list per locale', function () {
    $product = Product::factory()->create([
        'specs' => [
            'en' => [
                ['label' => 'Size', 'value' => '11 × 9 cm'],
                ['label' => 'Stitching', 'value' => 'Saddle stitch'],
            ],
            'az' => [
                ['label' => 'Ölçü', 'value' => '11 × 9 sm'],
                ['label' => 'Tikiş', 'value' => 'Sarrac tikişi'],
            ],
        ],
    ]);

    $fresh = Product::find($product->id);

    app()->setLocale('en');
    expect($fresh->specs)->toBe([
        ['label' => 'Size', 'value' => '11 × 9 cm'],
        ['label' => 'Stitching', 'value' => 'Saddle stitch'],
    ]);

    app()->setLocale('az');
    expect(Product::find($product->id)->specs)->toBe([
        ['label' => 'Ölçü', 'value' => '11 × 9 sm'],
        ['label' => 'Tikiş', 'value' => 'Sarrac tikişi'],
    ]);
});

it('falls back to another locale of specs when the active one is empty', function () {
    $product = Product::factory()->create([
        'specs' => ['en' => [['label' => 'Size', 'value' => '11 × 9 cm']], 'az' => []],
    ]);

    app()->setLocale('az');

    expect(Product::find($product->id)->specs)->toBe([['label' => 'Size', 'value' => '11 × 9 cm']]);
});

it('resolves an empty specs value as an empty list, not null', function () {
    $product = Product::factory()->create(['specs' => null]);

    expect(Product::find($product->id)->specs)->toBe([]);
});

it('exposes the raw per-locale specs map for admin editing', function () {
    $product = Product::factory()->create([
        'specs' => [
            'en' => [['label' => 'Size', 'value' => '11 × 9 cm']],
            'az' => [['label' => 'Ölçü', 'value' => '11 × 9 sm']],
        ],
    ]);

    expect(Product::find($product->id)->getSpecsTranslations())->toBe([
        'en' => [['label' => 'Size', 'value' => '11 × 9 cm']],
        'az' => [['label' => 'Ölçü', 'value' => '11 × 9 sm']],
    ]);
});

it('casts category to the ProductCategory enum', function () {
    $product = Product::factory()->create(['category' => 'wallet']);

    expect(Product::find($product->id)->category)->toBe(ProductCategory::Wallet);
});

it('casts tag to the ProductTag enum and allows null', function () {
    $product = Product::factory()->create(['tag' => 'low_stock']);
    expect(Product::find($product->id)->tag)->toBe(ProductTag::LowStock);

    $untagged = Product::factory()->create(['tag' => null]);
    expect(Product::find($untagged->id)->tag)->toBeNull();
});

it('rejects an unknown category value', function () {
    $product = Product::factory()->create();
    DB::table('products')->where('id', $product->id)->update(['category' => 'belt']);

    expect(fn () => Product::find($product->id)->category)->toThrow(\ValueError::class);
});

it('rejects an unknown tag value', function () {
    $product = Product::factory()->create();
    DB::table('products')->where('id', $product->id)->update(['tag' => 'clearance']);

    expect(fn () => Product::find($product->id)->tag)->toThrow(\ValueError::class);
});

it('labels the category and tag enums in Azerbaijani', function () {
    expect(ProductCategory::Wallet->label())->toBe('Cüzdan');
    expect(ProductCategory::Card->label())->toBe('Kart qabı');
    expect(ProductTag::New->label())->toBe('Yeni');
    expect(ProductTag::LowStock->label())->toBe('Az qalıb');
});
