<?php // tests/Feature/Catalog/ProductQuickAddEligibilityTest.php

use App\Domain\Catalog\Models\PersonalizationOption;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;

function productWithRelationsLoaded(int $id)
{
    return Product::with(['variants', 'personalizationOptions'])->findOrFail($id);
}

it('allows quick-add with exactly one active variant and no required personalization', function () {
    $product = Product::factory()->create();
    Variant::factory()->for($product)->create(['is_active' => true]);

    expect(productWithRelationsLoaded($product->id)->canQuickAdd())->toBeTrue();
});

it('refuses quick-add with more than one active variant', function () {
    $product = Product::factory()->create();
    Variant::factory()->for($product)->count(2)->create(['is_active' => true]);

    expect(productWithRelationsLoaded($product->id)->canQuickAdd())->toBeFalse();
});

it('refuses quick-add with a required personalization option', function () {
    $product = Product::factory()->create();
    Variant::factory()->for($product)->create(['is_active' => true]);
    PersonalizationOption::create([
        'product_id' => $product->id,
        'type' => 'monogram',
        'label' => 'Monogram',
        'price_delta_minor' => 0,
        'max_characters' => 3,
        'allowed_pattern' => '/^[A-Z]+$/',
        'is_required' => true,
    ]);

    expect(productWithRelationsLoaded($product->id)->canQuickAdd())->toBeFalse();
});

it('allows quick-add alongside a non-required personalization option', function () {
    $product = Product::factory()->create();
    Variant::factory()->for($product)->create(['is_active' => true]);
    PersonalizationOption::create([
        'product_id' => $product->id,
        'type' => 'monogram',
        'label' => 'Monogram',
        'price_delta_minor' => 0,
        'max_characters' => 3,
        'allowed_pattern' => '/^[A-Z]+$/',
        'is_required' => false,
    ]);

    expect(productWithRelationsLoaded($product->id)->canQuickAdd())->toBeTrue();
});

it('ignores inactive variants when counting toward eligibility', function () {
    $product = Product::factory()->create();
    Variant::factory()->for($product)->create(['is_active' => true]);
    Variant::factory()->for($product)->create(['is_active' => false]);

    expect(productWithRelationsLoaded($product->id)->canQuickAdd())->toBeTrue();
});

it('refuses quick-add with zero active variants', function () {
    $product = Product::factory()->create();
    Variant::factory()->for($product)->create(['is_active' => false]);

    expect(productWithRelationsLoaded($product->id)->canQuickAdd())->toBeFalse();
});
