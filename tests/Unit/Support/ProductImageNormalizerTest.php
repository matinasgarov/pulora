<?php // tests/Unit/Support/ProductImageNormalizerTest.php

use App\Support\ProductImageNormalizer;

/**
 * Writes a white JPEG with one black rectangle on it, standing in for a product
 * photographed on a white sweep.
 */
function fixturePhoto(int $canvasW, int $canvasH, int $productW, int $productH): string
{
    $image = imagecreatetruecolor($canvasW, $canvasH);
    imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
    imagefilledrectangle(
        $image,
        (int) (($canvasW - $productW) / 2),
        (int) (($canvasH - $productH) / 2),
        (int) (($canvasW - $productW) / 2) + $productW - 1,
        (int) (($canvasH - $productH) / 2) + $productH - 1,
        imagecolorallocate($image, 20, 20, 20),
    );

    $path = tempnam(sys_get_temp_dir(), 'src').'.jpg';
    imagejpeg($image, $path, 95);

    return $path;
}

/** Share of the output frame that is not background. */
function coverage(string $path): float
{
    $image = imagecreatefromjpeg($path);
    $w = imagesx($image);
    $h = imagesy($image);
    $product = 0;

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $rgb = imagecolorat($image, $x, $y);

            if ((($rgb >> 16) & 0xFF) < 244) {
                $product++;
            }
        }
    }

    return $product / ($w * $h);
}

it('gives differently-shaped products the same share of the frame', function () {
    // The real supplier set: portrait, square, landscape, and every framing from
    // edge-to-edge to lost in a wide white margin.
    $shapes = [
        'tall portrait' => [800, 1400, 400, 1200],
        'square, filling the canvas' => [1024, 1024, 1000, 1000],
        'square, small in frame' => [1024, 1024, 300, 300],
        'wide landscape' => [1530, 688, 900, 400],
    ];

    $coverages = [];

    foreach ($shapes as $label => [$canvasW, $canvasH, $productW, $productH]) {
        $target = tempnam(sys_get_temp_dir(), 'out').'.jpg';

        expect(app(ProductImageNormalizer::class)->normalize(
            fixturePhoto($canvasW, $canvasH, $productW, $productH),
            $target,
        ))->toBeTrue();

        $coverages[$label] = coverage($target);
    }

    // Before this was sized by area, the same four shapes spanned 13% to 58% of
    // the frame, which is what made the grid look uneven.
    //
    // They cannot all land on exactly the same figure: a 2.25:1 landscape product
    // would have to be wider than the frame to cover 36% of a 4/5 portrait tile,
    // so it ends up limited by MAX_EDGE instead. That is geometry, not a defect.
    // What matters is that the spread collapses.
    foreach ($coverages as $label => $share) {
        expect($share)->toBeGreaterThan(0.27, $label)->toBeLessThan(0.40, $label);
    }

    expect(max($coverages) / min($coverages))->toBeLessThan(1.4);
});

it('produces one canvas size regardless of the source shape', function () {
    foreach ([[800, 1400], [1024, 1024], [1530, 688]] as [$w, $h]) {
        $target = tempnam(sys_get_temp_dir(), 'out').'.jpg';
        app(ProductImageNormalizer::class)->normalize(fixturePhoto($w, $h, 400, 300), $target);

        expect(array_slice(getimagesize($target), 0, 2))->toBe([1200, 1500]);
    }
});

it('keeps the whole image when there is no product to find', function () {
    $blank = fixturePhoto(600, 600, 1, 1);
    $target = tempnam(sys_get_temp_dir(), 'out').'.jpg';

    // A crop to nothing would be a divide-by-zero or a 1px smear; it must not be
    // either.
    expect(app(ProductImageNormalizer::class)->normalize($blank, $target))->toBeTrue();
    expect(array_slice(getimagesize($target), 0, 2))->toBe([1200, 1500]);
});
