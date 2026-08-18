<?php // tests/Feature/Storefront/RelatedProductsTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\ProductCategory;

function piece2(string $en, ProductCategory $category): Product
{
    return Product::factory()->create([
        'is_active' => true,
        'name' => ['en' => $en, 'az' => $en],
        'slug' => Illuminate\Support\Str::slug($en),
        'category' => $category->value,
    ]);
}

it('offers the same piece in another colour before anything else', function () {
    // Ordering by id alone showed every visitor the same three products, on a
    // card case page as readily as on a bag one.
    $subject = piece2('Presidential Bifold — Black', ProductCategory::Wallet);
    piece2('Aran Dopp Kit — Walnut', ProductCategory::Bag);          // lowest id, wrong family
    piece2('Aran Dopp Kit — Black', ProductCategory::Bag);
    $sibling = piece2('Presidential Bifold — Cognac', ProductCategory::Wallet);

    $related = $this->get('/en/product/'.$subject->slug)->viewData('related');

    expect($related->first()->slug)->toBe($sibling->slug);
});

it('falls back to the same category before anything else', function () {
    $subject = piece2('Lone Piece — Black', ProductCategory::Card);
    piece2('Aran Dopp Kit — Walnut', ProductCategory::Bag);
    $sameCategory = piece2('Xezer Card Case — Walnut', ProductCategory::Card);

    $related = $this->get('/en/product/'.$subject->slug)->viewData('related');

    expect($related->first()->slug)->toBe($sameCategory->slug);
});

it('reads the family from the name, not from the last hyphen of the slug', function () {
    // "Document Holder — Black Python" slugs to document-holder-black-python.
    // Splitting on the last hyphen gives document-holder-black, which matches
    // no sibling at all — the bug this replaced.
    $subject = piece2('Document Holder — Black Python', ProductCategory::Card);
    piece2('Aran Dopp Kit — Walnut', ProductCategory::Bag);
    $sibling = piece2('Document Holder — Walnut', ProductCategory::Card);

    $related = $this->get('/en/product/'.$subject->slug)->viewData('related');

    expect($related->first()->slug)->toBe($sibling->slug);
});

it('never offers the product being looked at', function () {
    $subject = piece2('Presidential Bifold — Black', ProductCategory::Wallet);
    piece2('Presidential Bifold — Cognac', ProductCategory::Wallet);

    $related = $this->get('/en/product/'.$subject->slug)->viewData('related');

    expect($related->pluck('id'))->not->toContain($subject->id);
});
