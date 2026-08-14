<?php // tests/Feature/Admin/VariantTranslationTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

/**
 * The variant description is entered through a relation manager, which has no
 * page lifecycle and so wires its per-locale mapping to action-level hooks
 * instead. That is a different mechanism from the product form's, and it was
 * shipped without coverage — these tests are the regression guard on it.
 */
beforeEach(fn () => $this->actingAs(User::factory()->create(['is_operator' => true])));

function variantManager(Product $product): object
{
    return livewire(VariantsRelationManager::class, [
        'ownerRecord' => $product->fresh(),
        'pageClass' => EditProduct::class,
    ]);
}

it('fills the edit form from both locales', function () {
    $product = Product::factory()->create();
    $variant = Variant::factory()->for($product)->create([
        'description' => ['en' => 'Cognac / natural thread', 'az' => 'Konyak / təbii sap'],
    ]);

    variantManager($product)
        ->mountAction(TestAction::make('edit')->table($variant))
        ->assertSchemaStateSet([
            'description_en' => 'Cognac / natural thread',
            'description_az' => 'Konyak / təbii sap',
        ]);
});

it('keeps the other locale when only one is edited', function () {
    $product = Product::factory()->create();
    $variant = Variant::factory()->for($product)->create([
        'description' => ['en' => 'Cognac / natural thread', 'az' => 'Konyak / təbii sap'],
    ]);

    variantManager($product)
        ->callAction(
            TestAction::make('edit')->table($variant),
            ['description_en' => 'Chestnut / natural thread'],
        )
        ->assertHasNoActionErrors();

    expect($variant->fresh()->getTranslations('description'))
        ->toBe(['en' => 'Chestnut / natural thread', 'az' => 'Konyak / təbii sap']);
});

it('stores both locales from the create action', function () {
    $product = Product::factory()->create();

    variantManager($product)
        ->callAction(TestAction::make('create')->table(), [
            'sku' => 'BW-COG-01',
            'description_en' => 'Cognac / natural thread',
            'description_az' => 'Konyak / təbii sap',
            'stock_quantity' => 1,
            'weight_grams' => 120,
            'is_active' => true,
        ])
        ->assertHasNoActionErrors();

    expect(Variant::where('sku', 'BW-COG-01')->sole()->getTranslations('description'))
        ->toBe(['en' => 'Cognac / natural thread', 'az' => 'Konyak / təbii sap']);
});

// A description written before the translatable migration — or by any factory
// still passing a bare string — is plain text, not a per-locale array. Reading
// it as empty blanked the field, and saving the blank form destroyed it.
it('shows a plain-string description rather than blanking the field', function () {
    $product = Product::factory()->create();
    $variant = Variant::factory()->for($product)->create();

    DB::table('variants')->where('id', $variant->id)
        ->update(['description' => 'Cognac / natural thread']);

    variantManager($product)
        ->mountAction(TestAction::make('edit')->table($variant))
        ->assertSchemaStateSet(['description_en' => 'Cognac / natural thread']);
});

it('does not destroy a plain-string description on save', function () {
    $product = Product::factory()->create();
    $variant = Variant::factory()->for($product)->create();

    DB::table('variants')->where('id', $variant->id)
        ->update(['description' => 'Cognac / natural thread']);

    variantManager($product)
        ->callAction(TestAction::make('edit')->table($variant), ['sku' => 'BW-COG-02'])
        ->assertHasNoActionErrors();

    expect($variant->fresh()->description)->toBe('Cognac / natural thread');
});
