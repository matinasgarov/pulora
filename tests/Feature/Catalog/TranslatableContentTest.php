<?php // tests/Feature/Catalog/TranslatableContentTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
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

it('records nothing on orders.locale by default', function () {
    expect(Schema::hasColumn('orders', 'locale'))->toBeTrue();
});
