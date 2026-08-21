<?php // app/Support/ProductImageDerivatives.php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Smaller copies of a normalized product photograph, for the places that do not
 * need a 1200x1500 one.
 *
 * The grid served the full file into a tile that displays around 400px wide,
 * and the product gallery served it again into a 68px thumbnail — nine times
 * and three hundred times the pixels those frames can use. Page one of the
 * catalogue was 1.7MB of photographs, all of it fetched before anything could
 * be scrolled.
 *
 * WebP is written beside the JPEG rather than instead of it: it is roughly a
 * third of the size at the same visible quality, but a <picture> needs a
 * fallback for anything that cannot read it.
 */
class ProductImageDerivatives
{
    /**
     * TILE feeds the catalogue grid, THUMB the gallery's filmstrip. Both are
     * comfortably above their display size so they stay sharp on a 2x screen.
     */
    public const TILE = 600;

    public const THUMB = 160;

    public const WIDTHS = [self::TILE, self::THUMB];

    /** Quality is per format: WebP holds up at a lower number than JPEG does. */
    private const JPEG_QUALITY = 82;

    private const WEBP_QUALITY = 78;

    /** The derivative filename for one width and format, from the original's. */
    public static function name(string $filename, int $width, string $extension): string
    {
        return pathinfo($filename, PATHINFO_FILENAME).'-'.$width.'.'.$extension;
    }

    /**
     * Every derivative filename for an original, in no particular order.
     *
     * @return list<string>
     */
    public static function names(string $filename): array
    {
        $names = [];

        foreach (self::WIDTHS as $width) {
            foreach (['jpg', 'webp'] as $extension) {
                $names[] = self::name($filename, $width, $extension);
            }
        }

        return $names;
    }

    /**
     * Write every derivative of $jpegPath beside it.
     *
     * Returns the filenames written, so a caller pruning its output directory
     * knows these are wanted. Returns [] when the source cannot be read, which
     * leaves the original in place and the page falling back to it.
     *
     * @return list<string>
     */
    public function generate(string $jpegPath): array
    {
        $image = @imagecreatefromjpeg($jpegPath);

        if ($image === false) {
            return [];
        }

        $directory = dirname($jpegPath);
        $filename = basename($jpegPath);
        $written = [];

        foreach (self::WIDTHS as $width) {
            // Never upscale: a photograph smaller than the target would be
            // stretched, and the "smaller" copy would be the larger file.
            $scaled = imagesx($image) <= $width ? $image : imagescale($image, $width);

            $jpeg = self::name($filename, $width, 'jpg');
            $webp = self::name($filename, $width, 'webp');

            imagejpeg($scaled, $directory.DIRECTORY_SEPARATOR.$jpeg, self::JPEG_QUALITY);
            imagewebp($scaled, $directory.DIRECTORY_SEPARATOR.$webp, self::WEBP_QUALITY);

            $written[] = $jpeg;
            $written[] = $webp;
        }

        return $written;
    }

    /**
     * Copy an original's derivatives from $source to $target if they are there,
     * otherwise build them from the copy that has already been written.
     *
     * Deploys take the first path: the derivatives sit beside the committed
     * fixtures, so a container boot copies four small files instead of running
     * 288 image encodes on a shared CPU. Seeding from the raw supplier folder
     * takes the second.
     *
     * @return list<string>
     */
    public function copyOrGenerate(string $filename, string $source, string $target): array
    {
        $written = [];

        foreach (self::names($filename) as $name) {
            $from = $source.DIRECTORY_SEPARATOR.$name;

            if (! File::exists($from)) {
                return $this->generate($target.DIRECTORY_SEPARATOR.$filename);
            }

            File::copy($from, $target.DIRECTORY_SEPARATOR.$name);
            $written[] = $name;
        }

        return $written;
    }
}
