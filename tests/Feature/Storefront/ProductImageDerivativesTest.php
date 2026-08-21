<?php // tests/Feature/Storefront/ProductImageDerivativesTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use App\Support\ProductImageDerivatives;
use Illuminate\Support\Facades\File;

// Page one of the catalogue was 1.7MB of photographs: twelve 1200x1500 JPEGs
// served into tiles about 400px wide, all fetched before anything could be
// scrolled. The grid now asks for a 600px copy, and WebP where the browser
// takes it.
beforeEach(function () {
    $this->dir = public_path('storage/card-holders');
    $this->name = 'test-deriv-'.uniqid();

    File::ensureDirectoryExists($this->dir);

    $canvas = imagecreatetruecolor(900, 1125);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 240, 233, 221));
    imagefilledrectangle($canvas, 150, 220, 750, 900, imagecolorallocate($canvas, 60, 40, 30));
    imagejpeg($canvas, $this->dir.'/'.$this->name.'.jpg', 88);
    unset($canvas);

    $this->product = Product::factory()->create(['is_active' => true]);

    ProductImage::create([
        'product_id' => $this->product->id,
        'path' => 'card-holders/'.$this->name.'.jpg',
        'alt_text' => ['en' => 'A test piece', 'az' => 'Test'],
        'sort_order' => 0,
    ]);
});

afterEach(function () {
    foreach (File::glob($this->dir.'/'.$this->name.'*') as $file) {
        File::delete($file);
    }
});

it('writes a smaller jpeg and a smaller webp for each width', function () {
    $written = (new ProductImageDerivatives)->generate($this->dir.'/'.$this->name.'.jpg');

    expect($written)->toHaveCount(4);

    $original = filesize($this->dir.'/'.$this->name.'.jpg');

    foreach (ProductImageDerivatives::WIDTHS as $width) {
        $jpeg = $this->dir.'/'.ProductImageDerivatives::name($this->name.'.jpg', $width, 'jpg');
        $webp = $this->dir.'/'.ProductImageDerivatives::name($this->name.'.jpg', $width, 'webp');

        expect(file_exists($jpeg))->toBeTrue()
            ->and(file_exists($webp))->toBeTrue()
            ->and(getimagesize($jpeg)[0])->toBe($width)
            ->and(filesize($jpeg))->toBeLessThan($original)
            // The whole reason both formats are written: if WebP were not
            // meaningfully smaller there would be no point carrying two.
            ->and(filesize($webp))->toBeLessThan(filesize($jpeg));
    }
});

it('never upscales a photograph that is already small', function () {
    // A stretched "smaller" copy that is a bigger file than the original is the
    // failure mode worth naming.
    $small = $this->dir.'/'.$this->name.'-src.jpg';
    imagejpeg(imagecreatetruecolor(120, 150), $small, 88);

    (new ProductImageDerivatives)->generate($small);

    $tile = $this->dir.'/'.ProductImageDerivatives::name($this->name.'-src.jpg', ProductImageDerivatives::TILE, 'jpg');

    expect(getimagesize($tile)[0])->toBe(120);
});

it('serves the 600px webp on the catalogue grid', function () {
    (new ProductImageDerivatives)->generate($this->dir.'/'.$this->name.'.jpg');

    $html = $this->get('/en')->assertOk()->getContent();

    expect($html)->toContain($this->name.'-600.webp')
        ->and($html)->toContain($this->name.'-600.jpg')
        ->and($html)->toContain('type="image/webp"')
        // The full-size file must not be on the grid at all.
        ->and($html)->not->toContain('card-holders/'.$this->name.'.jpg')
        // Anchored to this tile's own markup: asserting the page contains
        // loading="lazy" anywhere passes on the gallery's images even when the
        // grid has none.
        ->and($html)->toContain($this->name.'-600.jpg')
        ->and($html)->toMatch('/'.preg_quote($this->name, '/').'-600\.jpg"[^>]*loading="lazy"/');
});

it('falls back to the original when a product has no derivatives', function () {
    // A photograph added through the admin panel: nothing generates derivatives
    // for it, and a missing file must mean a heavier page, never a broken one.
    $html = $this->get('/en')->assertOk()->getContent();

    expect($html)->toContain('card-holders/'.$this->name.'.jpg')
        ->and($html)->not->toContain($this->name.'-600');
});
