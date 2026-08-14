<?php // tests/Feature/Admin/ProductTranslationTest.php

use App\Domain\Catalog\Models\Product;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_operator' => true]));
});

it('stores both locales from the create form', function () {
    livewire(CreateProduct::class)
        ->fillForm([
            'name_en' => 'Card holder',
            'name_az' => 'Kart qabı',
            'slug' => 'card-holder',
            'base_price_minor' => '49.99',
            'lead_time_days' => 3,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::where('slug', 'card-holder')->sole();

    expect($product->getTranslations('name'))
        ->toBe(['en' => 'Card holder', 'az' => 'Kart qabı']);
});

it('fills the edit form from both locales', function () {
    $product = Product::factory()->create([
        'name' => ['en' => 'Bifold wallet', 'az' => 'İkiqat pulqabı'],
    ]);

    livewire(EditProduct::class, ['record' => $product->getKey()])
        ->assertFormSet([
            'name_en' => 'Bifold wallet',
            'name_az' => 'İkiqat pulqabı',
        ]);
});

it('requires the default locale but allows the other to be blank', function () {
    livewire(CreateProduct::class)
        ->fillForm([
            'name_en' => '',
            'name_az' => 'Kart qabı',
            'slug' => 'x',
            'base_price_minor' => '10.00',
        ])
        ->call('create')
        ->assertHasFormErrors(['name_en']);
});

it('saves an English-only product without complaining', function () {
    livewire(CreateProduct::class)
        ->fillForm([
            'name_en' => 'Belt',
            'name_az' => '',
            'slug' => 'belt',
            'base_price_minor' => '30.00',
            'lead_time_days' => 3,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Product::where('slug', 'belt')->sole()->getTranslations('name')['en'])->toBe('Belt');
});
