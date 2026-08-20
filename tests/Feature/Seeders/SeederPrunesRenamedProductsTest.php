<?php // tests/Feature/Seeders/SeederPrunesRenamedProductsTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use App\Domain\Catalog\ProductCategory;
use Database\Seeders\WalletCatalogue;
use Database\Seeders\WalletImagesSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

// Product::firstOrNew() in WalletImagesSeeder keys on slug. Renaming a piece
// in WalletCatalogue.php changes its slug, so a rename does not update the
// existing row — it inserts a second one under the new slug and leaves the
// first behind: active, in the shop, under the old name, holding the same
// photographs. Renaming "Presidential" to "The President" produced five such
// pairs, caught only by looking at the grid.
beforeEach(function () {
    $this->source = sys_get_temp_dir().DIRECTORY_SEPARATOR.'seed-src-'.uniqid();
    $this->target = sys_get_temp_dir().DIRECTORY_SEPARATOR.'seed-out-'.uniqid();

    File::ensureDirectoryExists($this->source);

    $canvas = imagecreatetruecolor(600, 800);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    imagefilledrectangle($canvas, 200, 300, 400, 500, imagecolorallocate($canvas, 30, 25, 20));
    imagejpeg($canvas, $this->source.DIRECTORY_SEPARATOR.'x1.jpg', 90);

    $this->currentSlug = Str::slug(WalletCatalogue::all()['x']['name']['en']);

    $this->seed = fn () => (new WalletImagesSeeder($this->source, $this->target))->run();
});

afterEach(function () {
    File::deleteDirectory($this->source);
    File::deleteDirectory($this->target);
});

it('marks a product it creates as its own, so a later rename can find it', function () {
    ($this->seed)();

    expect(Product::where('slug', $this->currentSlug)->value('seeded'))->toBeTrue();
});

it('prunes its own product when a rename leaves the old slug behind', function () {
    // Stands in for the row a previous run left under the pre-rename name —
    // same shape a real one would have: seeded, active, with an image.
    $stale = Product::create([
        'name' => ['en' => 'Presidential Bifold — Black', 'az' => 'Prezident Cüzdanı — Qara'],
        'description' => ['en' => 'x', 'az' => 'x'],
        'story' => ['en' => 'x', 'az' => 'x'],
        'leather' => ['en' => 'x', 'az' => 'x'],
        'category' => ProductCategory::Wallet->value,
        'base_price_minor' => 5500,
        'slug' => 'presidential-bifold-black',
        'lead_time_days' => 5,
        'is_active' => true,
        'seeded' => true,
        'specs' => ['en' => [], 'az' => []],
    ]);

    ProductImage::create([
        'product_id' => $stale->id,
        'path' => 'card-holders/f1.jpg',
        'alt_text' => ['en' => 'x', 'az' => 'x'],
        'sort_order' => 0,
    ]);

    ($this->seed)();

    expect(Product::find($stale->id))->toBeNull()
        ->and(ProductImage::where('product_id', $stale->id)->exists())->toBeFalse();
});

it('never prunes a product it did not create, whatever its slug looks like', function () {
    // Same slug shape as a stale seeded row — the only difference is who made
    // it. If the prune keyed on the slug pattern instead of this flag, an
    // operator's own product with an unlucky name would vanish on a redeploy.
    $handAdded = Product::create([
        'name' => ['en' => 'Presidential Suite Key Fob', 'az' => 'x'],
        'description' => ['en' => 'x', 'az' => 'x'],
        'story' => ['en' => 'x', 'az' => 'x'],
        'leather' => ['en' => 'x', 'az' => 'x'],
        'category' => ProductCategory::Card->value,
        'base_price_minor' => 2000,
        'slug' => 'presidential-suite-key-fob',
        'lead_time_days' => 5,
        'is_active' => true,
        'seeded' => false,
        'specs' => ['en' => [], 'az' => []],
    ]);

    ($this->seed)();

    expect(Product::find($handAdded->id))->not->toBeNull();
});
