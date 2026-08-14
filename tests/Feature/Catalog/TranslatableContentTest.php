<?php // tests/Feature/Catalog/TranslatableContentTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('round-trips both locales through the database', function () {
    $product = Product::factory()->create([
        'name' => ['en' => 'Bifold wallet', 'az' => 'İkiqat pulqabı'],
    ]);

    $fresh = Product::find($product->id);

    app()->setLocale('az');
    expect($fresh->name)->toBe('İkiqat pulqabı');

    app()->setLocale('en');
    expect($fresh->name)->toBe('Bifold wallet');
});

it('keeps a variant description translatable', function () {
    $variant = Variant::factory()->for(Product::factory())->create([
        'description' => ['en' => 'Cognac / natural thread', 'az' => 'Konyak / təbii sap'],
    ]);

    app()->setLocale('az');

    expect(Variant::find($variant->id)->description)->toBe('Konyak / təbii sap');
});

it('still accepts a plain string from an older factory', function () {
    $product = Product::factory()->create(['name' => 'Card holder']);

    app()->setLocale('az');

    expect(Product::find($product->id)->name)->toBe('Card holder');
});

// The factory JSON-encodes a bare string on the way in, so the test above only
// proves a string survives a round trip through Eloquent. These two plant text
// the cast has never seen — legacy rows, a raw SQL import, a tinker fix — which
// is the case the migration exists to clean up and the trait exists to survive.
it('resolves raw text that was never wrapped as JSON', function () {
    $product = Product::factory()->create(['name' => 'Placeholder']);

    DB::table('products')->where('id', $product->id)->update(['name' => 'Raw bifold wallet']);

    expect(Product::find($product->id)->name)->toBe('Raw bifold wallet');
});

it('reports raw text as fallback-locale content rather than nothing', function () {
    $product = Product::factory()->create(['name' => 'Placeholder']);

    DB::table('products')->where('id', $product->id)->update(['name' => 'Raw bifold wallet']);

    expect(Product::find($product->id)->getTranslations('name'))
        ->toBe(['en' => 'Raw bifold wallet']);
});

it('records nothing on orders.locale by default', function () {
    expect(Schema::hasColumn('orders', 'locale'))->toBeTrue();
});
