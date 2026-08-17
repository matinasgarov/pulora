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

    /** Share of the frame the product fills. The rest is the breathing room the
     *  design calls for — emptiness inside the frame, not between frames. */
    private const FILL = 0.76;

    /** A pixel this close to white counts as background, not product. Shadows
     *  are softer than this and survive, which is what keeps a product from
     *  looking cut out and pasted down. */
    private const WHITE_THRESHOLD = 244;

    public function normalize(string $sourcePath, string $targetPath): bool
    {
        $image = $this->read($sourcePath);

        if ($image === null) {
            return false;
        }

        [$left, $top, $right, $bottom] = $this->productBounds($image);

        $cropW = max(1, $right - $left + 1);
        $cropH = max(1, $bottom - $top + 1);

        // Scale so the longer edge lands on FILL of the canvas — `contain`, not
        // `cover`, because cropping a wallet to fill a frame cuts the product.
        $scale = min(
            (self::CANVAS_W * self::FILL) / $cropW,
            (self::CANVAS_H * self::FILL) / $cropH
        );

        $drawW = max(1, (int) round($cropW * $scale));
        $drawH = max(1, (int) round($cropH * $scale));

        $canvas = imagecreatetruecolor(self::CANVAS_W, self::CANVAS_H);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));

        imagecopyresampled(
            $canvas, $image,
            (int) round((self::CANVAS_W - $drawW) / 2),
            (int) round((self::CANVAS_H - $drawH) / 2),
            $left, $top,
            $drawW, $drawH,
            $cropW, $cropH
        );

        imagejpeg($canvas, $targetPath, 88);

        imagedestroy($canvas);
        imagedestroy($image);

        return true;
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

    /** @return array{int,int,int,int} left, top, right, bottom */
    private function productBounds(\GdImage $image): array
    {
        $w = imagesx($image);
        $h = imagesy($image);

        $left = $w;
        $top = $h;
        $right = -1;
        $bottom = -1;

        // Every 2nd pixel: the bounding box of an object hundreds of pixels
        // across does not move for a half-pixel of extra precision.
        for ($y = 0; $y < $h; $y += 2) {
            for ($x = 0; $x < $w; $x += 2) {
                $rgb = imagecolorat($image, $x, $y);

                if ((($rgb >> 16) & 0xFF) >= self::WHITE_THRESHOLD
                    && (($rgb >> 8) & 0xFF) >= self::WHITE_THRESHOLD
                    && ($rgb & 0xFF) >= self::WHITE_THRESHOLD) {
                    continue;
                }

                $left = min($left, $x);
                $top = min($top, $y);
                $right = max($right, $x);
                $bottom = max($bottom, $y);
            }
        }

        // An image that is entirely background: keep it whole rather than
        // cropping to nothing.
        if ($right < 0) {
            return [0, 0, $w - 1, $h - 1];
        }

        return [$left, $top, $right, $bottom];
    }
}
