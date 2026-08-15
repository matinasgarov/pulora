<?php // tests/Feature/Storefront/PersonalizationErrorTest.php

use App\Domain\Catalog\Models\PersonalizationOption;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Livewire\ProductPurchase;

use function Pest\Livewire\livewire;

/**
 * The error has to land on the input the customer got wrong.
 *
 * This used to be recovered by matching the exception's message text back to an
 * option's label. `personalization_options.label` has no uniqueness constraint,
 * so a product with "Name" and "Name Tag" put every "Name Tag" error onto the
 * "Name" field — the customer saw a complaint about an input they had filled in
 * correctly, nothing at all on the one that was wrong, and no way to work out
 * why "Add to bag" did nothing.
 */
function productWithOptions(array $options): Product
{
    $product = Product::factory()->create(['is_active' => true, 'base_price_minor' => 8900]);

    Variant::factory()->for($product)->create([
        'is_active' => true,
        'stock_quantity' => 5,
        'weight_grams' => 120,
    ]);

    foreach ($options as $attributes) {
        PersonalizationOption::create($attributes + [
            'product_id' => $product->id,
            'max_characters' => 3,
            'allowed_pattern' => '/^[A-Z]+$/',
            'is_required' => false,
            'price_delta_minor' => 0,
        ]);
    }

    return $product->fresh();
}

it('puts the error on the offending option when one label prefixes another', function () {
    $product = productWithOptions([
        ['type' => 'name', 'label' => 'Name'],
        ['type' => 'name_tag', 'label' => 'Name Tag'],
    ]);

    livewire(ProductPurchase::class, ['product' => $product])
        ->set('personalization.name', 'AB')          // valid
        ->set('personalization.name_tag', 'TOOLONG') // violates max_characters
        ->call('add')
        ->assertHasErrors('personalization.name_tag')
        ->assertHasNoErrors('personalization.name');
});

it('puts a required-field error on the field that is required', function () {
    $product = productWithOptions([
        ['type' => 'monogram', 'label' => 'Monogram', 'is_required' => true],
    ]);

    livewire(ProductPurchase::class, ['product' => $product])
        ->call('add')
        ->assertHasErrors('personalization.monogram');
});

it('puts a disallowed-character error on the right field', function () {
    $product = productWithOptions([
        ['type' => 'initials', 'label' => 'Initials'],
        ['type' => 'note', 'label' => 'Note'],
    ]);

    livewire(ProductPurchase::class, ['product' => $product])
        ->set('personalization.note', '123')
        ->call('add')
        ->assertHasErrors('personalization.note')
        ->assertHasNoErrors('personalization.initials');
});

it('does not add the line when personalization is rejected', function () {
    $product = productWithOptions([
        ['type' => 'monogram', 'label' => 'Monogram', 'is_required' => true],
    ]);

    livewire(ProductPurchase::class, ['product' => $product])->call('add');

    expect(app(App\Domain\Cart\CartService::class)->snapshot()->lines)->toBeEmpty();
});
