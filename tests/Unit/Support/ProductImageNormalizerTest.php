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

/**
 * Share of the output frame that is not background.
 *
 * The cutoff is well below the sweep colour rather than just below white: the
 * normalizer tints its background to --color-ground, so "anything not white" now
 * describes the whole frame.
 */
function coverage(string $path): float
{
    $image = imagecreatefromjpeg($path);
    $w = imagesx($image);
    $h = imagesy($image);
    $product = 0;

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            if (((imagecolorat($image, $x, $y) >> 16) & 0xFF) < 200) {
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

it('tints sweeps to the same colour the page ground uses', function () {
    // The normalizer bakes the ground colour into the JPEGs at seed time, so it
    // cannot read the CSS variable and has to hold its own copy. This is the
    // only thing stopping the two drifting apart, which would show up as every
    // product photograph sitting on a slightly wrong shade of the page.
    preg_match('/--color-ground:\s*#([0-9a-fA-F]{6});/', file_get_contents(resource_path('css/app.css')), $m);

    $sweep = (new ReflectionClass(ProductImageNormalizer::class))->getConstant('SWEEP');

    expect(sprintf('%02x%02x%02x', ...$sweep))->toBe(strtolower($m[1]),
        'ProductImageNormalizer::SWEEP no longer matches --color-ground. Update it and re-run WalletImagesSeeder.');
});

it('puts the page ground, not white, behind the product', function () {
    $target = tempnam(sys_get_temp_dir(), 'out').'.jpg';
    app(ProductImageNormalizer::class)->normalize(fixturePhoto(900, 900, 300, 300), $target);

    $image = imagecreatefromjpeg($target);
    $corner = imagecolorat($image, 8, 8);

    // Within a JPEG's rounding of the real thing.
    foreach ([[16, 0xf0], [8, 0xe9], [0, 0xdd]] as [$shift, $expected]) {
        expect(abs((($corner >> $shift) & 0xFF) - $expected))->toBeLessThanOrEqual(3);
    }
});

/**
 * The same product, once with a soft drop shadow beneath it and once without.
 * The shadow is a light grey band — the kind these supplier photographs
 * actually carry, not a hard black one.
 */
function photoWithShadow(bool $shadow): string
{
    $image = imagecreatetruecolor(900, 1200);
    imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));

    if ($shadow) {
        // Luma 220: darker than the sweep, far lighter than any leather. This
        // value is the point of the fixture — it sits between the cutoff the
        // old threshold produced (231) and the one the current threshold
        // produces (200), so it is caught as product by the first and ignored
        // by the second. A shadow outside that band would pass either way and
        // the test would prove nothing.
        imagefilledrectangle($image, 260, 810, 640, 980, imagecolorallocate($image, 220, 220, 220));
    }

    imagefilledrectangle($image, 300, 400, 600, 800, imagecolorallocate($image, 40, 30, 25));

    $path = tempnam(sys_get_temp_dir(), 'shadow').'.jpg';
    imagejpeg($image, $path, 95);

    return $path;
}

it('sizes a product by the product, not by the shadow under it', function () {
    // The brown card case x1 shipped at 27.7% of its tile and sitting high,
    // beside the identical piece in black at 34.9% and centred, because its
    // drop shadow was measured as part of the product: a box a quarter too
    // tall, then scaled down to hit a fixed share of the frame by area.
    $bare = tempnam(sys_get_temp_dir(), 'out').'.jpg';
    $shadowed = tempnam(sys_get_temp_dir(), 'out').'.jpg';

    app(ProductImageNormalizer::class)->normalize(photoWithShadow(false), $bare);
    app(ProductImageNormalizer::class)->normalize(photoWithShadow(true), $shadowed);

    expect(coverage($shadowed))->toEqualWithDelta(coverage($bare), 0.03);
});

it('leaves a shadowed product centred in its frame', function () {
    // Centring a box that includes the shadow lifts the product itself above
    // the middle, which is what made one tile in a row of three sit high.
    $target = tempnam(sys_get_temp_dir(), 'out').'.jpg';
    app(ProductImageNormalizer::class)->normalize(photoWithShadow(true), $target);

    $image = imagecreatefromjpeg($target);
    $w = imagesx($image);
    $h = imagesy($image);
    $top = $h;
    $bottom = -1;

    for ($y = 0; $y < $h; $y += 2) {
        for ($x = 0; $x < $w; $x += 2) {
            if ((imagecolorat($image, $x, $y) >> 16 & 0xFF) < 120) {
                $top = min($top, $y);
                $bottom = max($bottom, $y);
                break;
            }
        }
    }

    expect((($top + $bottom) / 2) / $h)->toEqualWithDelta(0.5, 0.04);
});
