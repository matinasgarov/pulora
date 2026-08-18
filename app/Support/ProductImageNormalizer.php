<?php // app/Support/ProductImageNormalizer.php

namespace App\Support;

/**
 * Makes a folder of supplier photographs look like one shoot.
 *
 * The source images arrive at anything from 0.63 to 2.22 aspect ratio, and each
 * one frames its product differently — some fill the canvas, some sit in a wide
 * white margin. Dropped into a fixed 4/5 tile they read as wildly different
 * sizes, which is what makes a grid of them look uneven.
 *
 * So: find the product by trimming the near-white surround, then re-place it on
 * a uniform canvas at a fixed proportion of the frame. Every product then
 * occupies the same share of its tile regardless of how it was shot.
 */
class ProductImageNormalizer
{
    /** 4/5 — the tile ratio the storefront grid uses. */
    private const CANVAS_W = 1200;

    private const CANVAS_H = 1500;

    /** Share of the frame's *area* the product occupies.
     *
     *  Sizing by area rather than by the longer edge is the whole trick. Fitting
     *  each product inside a 76%-of-frame box equalized the constraining edge and
     *  nothing else: a tall wallet hit 76% of the height and read as enormous,
     *  while a flat landscape one hit 76% of the width and covered a third as
     *  much frame. Measured across this set, coverage ranged from 13% to 58%.
     *  Matching area instead is what actually makes two differently-shaped
     *  products look like the same size. */
    private const TARGET_AREA = 0.36;

    /** Nothing may exceed this share of either edge, so a very flat product is
     *  held back from spanning the frame edge-to-edge chasing its area target. */
    private const MAX_EDGE = 0.90;

    /** How much darker than the sweep a pixel must be to count as product.
     *
     *  A fixed near-white cutoff was not enough: several of these sweeps are not
     *  white but a soft grey wash, which the cutoff read as product and which
     *  then dragged the bounding box across half the frame. Measuring the sweep
     *  per image and looking for a real step down from it is what separates a
     *  brown wallet from the grey it is sitting on. Wide enough that soft
     *  shadows still survive — they are what keeps a product from looking cut
     *  out and pasted down.
     *
     *  60, not 24. At 24 the cutoff lands around luma 231, which is inside the
     *  soft drop shadow several of these photographs carry — so the shadow was
     *  measured as part of the product. The brown card case x1 came out with a
     *  668x948 box against 662x754 for the identical piece in black, and since
     *  the product is then scaled to fill a fixed share of the frame by AREA, a
     *  box a quarter too tall renders the product visibly smaller and, because
     *  the box is centred, sitting high in its tile. Beside its own colourways
     *  that read plainly as broken. */
    private const PRODUCT_CONTRAST = 60;

    /** However dark the sweep reads, anything above this is still background. */
    private const BACKGROUND_FLOOR = 200;

    /** Ceiling on the white-point correction. A sweep darker than this is not a
     *  grey sweep, it is a deliberate backdrop, and washing it out would be a
     *  bigger change than the seam it fixes. */
    private const MAX_SWEEP_LIFT = 30;

    /**
     * The sweep is re-tinted to this, which is --color-ground.
     *
     * Duplicated from the stylesheet on purpose: this is baked into the JPEGs at
     * seed time, so it cannot read a CSS variable. If --color-ground changes,
     * re-run WalletImagesSeeder — a test pins the two together so the drift is
     * caught rather than discovered.
     */
    private const SWEEP = [0xf0, 0xe9, 0xdd];

    /** Luma band below the cutoff over which the re-tint fades out, so the
     *  product's edge is not ringed by a hard line where the recolour stops. */
    private const TINT_RAMP = 26;

    public function normalize(string $sourcePath, string $targetPath): bool
    {
        $image = $this->read($sourcePath);

        if ($image === null) {
            return false;
        }

        $background = $this->backgroundLevel($image);

        // Lift this photograph's sweep to true white. Without it a slightly grey
        // sweep survives the crop and shows as a lighter rectangle behind the
        // product, which is the seam between the photograph and our canvas. It
        // also pulls the whole set to one exposure, which is the point.
        if ($background < 255) {
            imagefilter($image, IMG_FILTER_BRIGHTNESS, min(self::MAX_SWEEP_LIFT, 255 - $background));
        }

        [$left, $top, $right, $bottom] = $this->productBounds($image, $background);

        $cropW = max(1, $right - $left + 1);
        $cropH = max(1, $bottom - $top + 1);

        // Scale so the product covers TARGET_AREA of the canvas, whatever its
        // shape. The geometric mean is the side of the square with that area, so
        // dividing the two gives the factor that lands it there.
        $scale = sqrt(self::CANVAS_W * self::CANVAS_H * self::TARGET_AREA)
            / sqrt($cropW * $cropH);

        // Then hold it inside the frame — `contain`, not `cover`, because
        // cropping a wallet to fill a frame cuts the product.
        $scale = min(
            $scale,
            (self::CANVAS_W * self::MAX_EDGE) / $cropW,
            (self::CANVAS_H * self::MAX_EDGE) / $cropH
        );

        $drawW = max(1, (int) round($cropW * $scale));
        $drawH = max(1, (int) round($cropH * $scale));

        $canvas = imagecreatetruecolor(self::CANVAS_W, self::CANVAS_H);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, ...self::SWEEP));

        $dstX = (int) round((self::CANVAS_W - $drawW) / 2);
        $dstY = (int) round((self::CANVAS_H - $drawH) / 2);

        imagecopyresampled(
            $canvas, $image,
            $dstX, $dstY,
            $left, $top,
            $drawW, $drawH,
            $cropW, $cropH
        );

        // Only the drawn rectangle needs re-tinting; the rest of the canvas was
        // filled with the sweep colour to begin with.
        $this->tintSweep($canvas, $dstX, $dstY, $drawW, $drawH);

        imagejpeg($canvas, $targetPath, 88);

        imagedestroy($canvas);
        imagedestroy($image);

        return true;
    }

    /**
     * Recolours the photograph's own sweep to the page's ground colour.
     *
     * The product is left alone; only the near-white surround is remapped, and
     * it is remapped rather than flooded so the shadow under the product keeps
     * its shape — a shadow is a darker sweep, and it stays a darker sweep of the
     * new colour. Below the cutoff the effect fades out over TINT_RAMP, because
     * stopping it abruptly draws a visible ring around the product.
     *
     * The alternative was to leave the images on white. On the old cream ground
     * that was invisible; on a brown one every tile became a bright white card.
     */
    private function tintSweep(\GdImage $canvas, int $x0, int $y0, int $w, int $h): void
    {
        $cutoff = 255 - self::MAX_SWEEP_LIFT;

        for ($y = $y0; $y < $y0 + $h; $y++) {
            for ($x = $x0; $x < $x0 + $w; $x++) {
                $rgb = imagecolorat($canvas, $x, $y);
                $luma = $this->luma($rgb);

                if ($luma < $cutoff - self::TINT_RAMP) {
                    continue;
                }

                // 0 at the bottom of the ramp, 1 once clearly in the sweep.
                $strength = min(1.0, ($luma - ($cutoff - self::TINT_RAMP)) / self::TINT_RAMP);
                $scale = $luma / 255;

                $mixed = [];

                foreach ([16, 8, 0] as $i => $shift) {
                    $original = ($rgb >> $shift) & 0xFF;
                    $tinted = self::SWEEP[$i] * $scale;
                    $mixed[] = (int) round($original + ($tinted - $original) * $strength);
                }

                imagesetpixel($canvas, $x, $y, imagecolorallocate($canvas, ...array_map(
                    fn (int $v): int => max(0, min(255, $v)),
                    $mixed
                )));
            }
        }
    }

    private function read(string $path): ?\GdImage
    {
        $info = @getimagesize($path);

        if ($info === false) {
            return null;
        }

        // .jfif is JPEG data under a different extension, so trust the sniffed
        // type rather than the filename.
        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => false,
        };

        if ($image === false) {
            return null;
        }

        // A transparent PNG would otherwise trim to nothing, since transparent
        // pixels are not near-white.
        if ($info[2] === IMAGETYPE_PNG) {
            $flattened = imagecreatetruecolor(imagesx($image), imagesy($image));
            imagefill($flattened, 0, 0, imagecolorallocate($flattened, 255, 255, 255));
            imagecopy($flattened, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            imagedestroy($image);
            $image = $flattened;
        }

        return $image;
    }

    /**
     * The product's bounds, ignoring stray marks.
     *
     * A plain min/max over every off-white pixel is too eager. Several of these
     * photographs carry a soft vignette or a shadow that drifts into a corner,
     * and one such pixel drags the box out to the full frame — after which the
     * product is scaled as though it were frame-sized and lands tiny. So count
     * off-white pixels per row and per column and keep only the rows and columns
     * carrying enough of them to be part of an object.
     *
     * @return array{int,int,int,int} left, top, right, bottom
     */
    private function productBounds(\GdImage $image, int $background): array
    {
        $w = imagesx($image);
        $h = imagesy($image);

        $rows = array_fill(0, $h, 0);
        $cols = array_fill(0, $w, 0);

        // Measured before the lift, applied after it — so step down from where
        // the sweep now sits, not where it started.
        $lifted = min(255, $background + min(self::MAX_SWEEP_LIFT, 255 - $background));
        $cutoff = max(self::BACKGROUND_FLOOR, $lifted - self::PRODUCT_CONTRAST);

        // Every 2nd pixel: the bounding box of an object hundreds of pixels
        // across does not move for a half-pixel of extra precision.
        for ($y = 0; $y < $h; $y += 2) {
            for ($x = 0; $x < $w; $x += 2) {
                if ($this->luma(imagecolorat($image, $x, $y)) > $cutoff) {
                    continue;
                }

                $rows[$y]++;
                $cols[$x]++;
            }
        }

        [$top, $bottom] = $this->span($rows, $w);
        [$left, $right] = $this->span($cols, $h);

        // An image that is entirely background: keep it whole rather than
        // cropping to nothing.
        if ($right < 0 || $bottom < 0) {
            return [0, 0, $w - 1, $h - 1];
        }

        return [$left, $top, $right, $bottom];
    }

    /**
     * The luminance of the sweep the product was photographed on.
     *
     * The sweep is whatever most of the picture is, so read it off the bright end
     * of the histogram — the 85th percentile, which even a large product cannot
     * outvote.
     */
    private function backgroundLevel(\GdImage $image): int
    {
        $w = imagesx($image);
        $h = imagesy($image);

        $histogram = array_fill(0, 256, 0);
        $samples = 0;

        for ($y = 0; $y < $h; $y += 4) {
            for ($x = 0; $x < $w; $x += 4) {
                $histogram[$this->luma(imagecolorat($image, $x, $y))]++;
                $samples++;
            }
        }

        $seen = 0;

        foreach ($histogram as $level => $count) {
            $seen += $count;

            if ($seen >= $samples * 0.85) {
                return $level;
            }
        }

        return 255;
    }

    private function luma(int $rgb): int
    {
        return (int) (
            0.299 * (($rgb >> 16) & 0xFF)
            + 0.587 * (($rgb >> 8) & 0xFF)
            + 0.114 * ($rgb & 0xFF)
        );
    }

    /**
     * First and last index whose count clears the noise floor.
     *
     * @param  array<int,int>  $counts
     * @param  int  $extent  length of the perpendicular axis, which sets the scale of the floor
     * @return array{int,int}
     */
    private function span(array $counts, int $extent): array
    {
        // 1.5% of a scanline. Below that it is a shadow edge or a compression
        // artefact, not the edge of a wallet.
        $floor = max(2, (int) ($extent / 2 * 0.015));

        $first = -1;
        $last = -1;

        foreach ($counts as $index => $count) {
            if ($count < $floor) {
                continue;
            }

            if ($first < 0) {
                $first = $index;
            }

            $last = $index;
        }

        return [$first, $last];
    }
}
