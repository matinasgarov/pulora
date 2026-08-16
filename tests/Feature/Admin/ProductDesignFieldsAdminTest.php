<?php // tests/Feature/Admin/ProductDesignFieldsAdminTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\ProductCategory;
use App\Domain\Catalog\ProductTag;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_operator' => true]));
});

it('stores leather, category, tag and specs in both locales from the create form', function () {
    livewire(CreateProduct::class)
        ->fillForm([
            'name_en' => 'Card holder',
            'name_az' => 'Kart qabı',
            'slug' => 'card-holder',
            'leather_en' => 'Calfskin · Dark brown',
            'leather_az' => 'Buzov · Tünd qəhvəyi',
            'category' => ProductCategory::Card->value,
            'tag' => ProductTag::New->value,
            'specs_en' => [['label' => 'Size', 'value' => '9.5 × 6.5 cm']],
            'specs_az' => [['label' => 'Ölçü', 'value' => '9.5 × 6.5 sm']],
            'base_price_minor' => '49.99',
            'lead_time_days' => 3,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::where('slug', 'card-holder')->sole();

    expect($product->getTranslations('leather'))->toBe([
        'en' => 'Calfskin · Dark brown',
        'az' => 'Buzov · Tünd qəhvəyi',
    ]);
    expect($product->category)->toBe(ProductCategory::Card);
    expect($product->tag)->toBe(ProductTag::New);
    expect($product->getSpecsTranslations())->toBe([
        'en' => [['label' => 'Size', 'value' => '9.5 × 6.5 cm']],
        'az' => [['label' => 'Ölçü', 'value' => '9.5 × 6.5 sm']],
    ]);
});

it('fills the edit form with leather, category, tag and specs from both locales', function () {
    $product = Product::factory()->create([
        'leather' => ['en' => 'Vegetable-tanned · Natural', 'az' => 'Bitkisel aşılanmış · Natural'],
        'category' => ProductCategory::Wallet->value,
        'tag' => ProductTag::LowStock->value,
        'specs' => [
            'en' => [['label' => 'Size', 'value' => '11 × 9 cm']],
            'az' => [['label' => 'Ölçü', 'value' => '11 × 9 sm']],
        ],
    ]);

    $component = livewire(EditProduct::class, ['record' => $product->getKey()])
        ->assertFormSet([
            'leather_en' => 'Vegetable-tanned · Natural',
            'leather_az' => 'Bitkisel aşılanmış · Natural',
            'category' => ProductCategory::Wallet->value,
            'tag' => ProductTag::LowStock->value,
        ]);

    // The repeater keys its rows by UUID, so specs_en/specs_az are compared
    // by value only, the same way the form itself is content-addressed.
    expect(array_values($component->get('data.specs_en')))
        ->toBe([['label' => 'Size', 'value' => '11 × 9 cm']]);
    expect(array_values($component->get('data.specs_az')))
        ->toBe([['label' => 'Ölçü', 'value' => '11 × 9 sm']]);
});

it('lets the operator clear a tag that no longer applies', function () {
    $product = Product::factory()->create(['tag' => ProductTag::New->value]);

    livewire(EditProduct::class, ['record' => $product->getKey()])
        ->fillForm(['tag' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->tag)->toBeNull();
});

it('saves a product with no leather, category, tag or specs at all', function () {
    livewire(CreateProduct::class)
        ->fillForm([
            'name_en' => 'Belt',
            'slug' => 'belt',
            'base_price_minor' => '30.00',
            'lead_time_days' => 3,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::where('slug', 'belt')->sole();

    expect($product->category)->toBeNull();
    expect($product->tag)->toBeNull();
    expect($product->leather)->toBe('');
    expect($product->specs)->toBe([]);
});
