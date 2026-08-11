<?php // tests/Feature/Admin/ProductResourceTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\User;
use Filament\Actions\Testing\TestAction;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_operator' => true]));
});

it('lists products', function () {
    $products = Product::factory()->count(3)->create();

    livewire(ListProducts::class)
        ->assertCanSeeTableRecords($products);
});

it('stores the price the operator typed as qepik', function () {
    livewire(CreateProduct::class)
        ->fillForm([
            'name' => 'Card holder',
            'slug' => 'card-holder',
            'base_price_minor' => '49.99',
            'lead_time_days' => 3,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Product::where('slug', 'card-holder')->sole()->base_price_minor)->toBe(4999);
});

it('does not drift the price when the record is saved without touching it', function () {
    $product = Product::factory()->create(['base_price_minor' => 4999]);

    livewire(EditProduct::class, ['record' => $product->getKey()])
        ->fillForm(['name' => 'Renamed wallet'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->base_price_minor)->toBe(4999);
});

it('rejects a product with no name', function () {
    livewire(CreateProduct::class)
        ->fillForm(['name' => '', 'slug' => 'x', 'base_price_minor' => '10.00'])
        ->call('create')
        ->assertHasFormErrors(['name']);
});

it('rejects a duplicate slug', function () {
    Product::factory()->create(['slug' => 'bifold']);

    livewire(CreateProduct::class)
        ->fillForm(['name' => 'Another', 'slug' => 'bifold', 'base_price_minor' => '10.00'])
        ->call('create')
        ->assertHasFormErrors(['slug']);
});

it('locks the slug once the product has been ordered', function () {
    $product = Product::factory()->create(['slug' => 'sold-wallet']);
    $variant = Variant::factory()->for($product)->create();
    OrderItem::factory()->for(Order::factory()->create())->create(['variant_id' => $variant->id]);

    livewire(EditProduct::class, ['record' => $product->getKey()])
        ->assertFormFieldIsDisabled('slug');
});

it('leaves the slug editable on a product nobody has ordered', function () {
    $product = Product::factory()->create();

    livewire(EditProduct::class, ['record' => $product->getKey()])
        ->assertFormFieldIsEnabled('slug');
});

it('hides the delete action on a product that has been ordered', function () {
    $product = Product::factory()->create();
    $variant = Variant::factory()->for($product)->create();
    OrderItem::factory()->for(Order::factory()->create())->create(['variant_id' => $variant->id]);

    livewire(EditProduct::class, ['record' => $product->getKey()])
        ->assertActionHidden('delete');

    expect(Product::find($product->id))->not->toBeNull();
});

it('allows the delete action on a product nobody has ordered', function () {
    $product = Product::factory()->create();

    livewire(EditProduct::class, ['record' => $product->getKey()])
        ->assertActionVisible('delete');
});

it('hides the variant delete action on a variant that has been ordered', function () {
    $product = Product::factory()->create();
    $variant = Variant::factory()->for($product)->create();
    OrderItem::factory()->for(Order::factory()->create())->create(['variant_id' => $variant->id]);

    livewire(VariantsRelationManager::class, [
        'ownerRecord' => $product,
        'pageClass' => EditProduct::class,
    ])->assertActionHidden(TestAction::make('delete')->table($variant));

    expect(Variant::find($variant->id))->not->toBeNull();
});

it('allows the variant delete action on a variant nobody has ordered', function () {
    $product = Product::factory()->create();
    $variant = Variant::factory()->for($product)->create();

    livewire(VariantsRelationManager::class, [
        'ownerRecord' => $product,
        'pageClass' => EditProduct::class,
    ])->assertActionVisible(TestAction::make('delete')->table($variant));
});
