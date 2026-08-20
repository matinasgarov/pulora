<?php
/**
 * Builds the favicon set from the brand mark.
 *
 * Run when the logo changes:  php scripts/build-icons.php
 *
 * Two things the source cannot be used for directly. It is a 500x500 canvas
 * with the mark occupying 283x329 in the middle, so a straight resize to 16px
 * spends a third of the frame on empty margin and the wallet becomes a smudge;
 * and the mark is dark brown on transparency, which disappears against a dark
 * browser tab strip. So: trim to the ink, pad evenly, and sit it on the shop's
 * own cream rather than on nothing.
 */

$source = $argv[1] ?? 'C:/Users/Matin Asgarov/Desktop/logos/logo-wallet-removebg-preview.png';
$public = __DIR__.'/../public';

// --color-ground from resources/css/app.css. The icon is a chip of the shop.
[$bgR, $bgG, $bgB] = [0xf0, 0xe9, 0xdd];

$src = imagecreatefrompng($source);
$w = imagesx($src);
$h = imagesy($src);

// Trim to what is actually drawn. alpha runs 0 (opaque) to 127 (transparent);
// 100 keeps the antialiased edge without catching the near-empty halo.
$minX = $w; $maxX = -1; $minY = $h; $maxY = -1;
for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        if (((imagecolorat($src, $x, $y) >> 24) & 0x7F) < 100) {
            $minX = min($minX, $x); $maxX = max($maxX, $x);
            $minY = min($minY, $y); $maxY = max($maxY, $y);
        }
    }
}

$markW = $maxX - $minX + 1;
$markH = $maxY - $minY + 1;

// Square, with the mark at 82% of the frame. Tighter and it touches the edges
// of a rounded home-screen icon; looser and it shrinks again at 16px.
$frame = (int) round(max($markW, $markH) / 0.82);

function render(int $size, bool $opaque, int $frame, int $markW, int $markH, int $minX, int $minY, $src, array $bg): \GdImage
{
    $canvas = imagecreatetruecolor($size, $size);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);

    $fill = $opaque
        ? imagecolorallocate($canvas, $bg[0], $bg[1], $bg[2])
        : imagecolorallocatealpha($canvas, $bg[0], $bg[1], $bg[2], 127);

    imagefilledrectangle($canvas, 0, 0, $size, $size, $fill);
    imagealphablending($canvas, true);

    $scale = $size / $frame;
    $dstW = (int) round($markW * $scale);
    $dstH = (int) round($markH * $scale);

    imagecopyresampled(
        $canvas, $src,
        (int) round(($size - $dstW) / 2), (int) round(($size - $dstH) / 2),
        $minX, $minY,
        $dstW, $dstH, $markW, $markH,
    );

    return $canvas;
}

$png = function (int $size, bool $opaque) use ($frame, $markW, $markH, $minX, $minY, $src, $bgR, $bgG, $bgB): string {
    $img = render($size, $opaque, $frame, $markW, $markH, $minX, $minY, $src, [$bgR, $bgG, $bgB]);
    ob_start();
    imagepng($img, null, 9);

    return ob_get_clean();
};

// The tab icon carries the cream: a dark mark on transparency is invisible on
// a dark tab strip, and there is no way to offer a browser two versions.
$icoSizes = [16, 32, 48];
$icoImages = array_map(fn (int $s) => $png($s, true), $icoSizes);

// ICONDIR, then one 16-byte ICONDIRENTRY each, then the PNG payloads.
$offset = 6 + 16 * count($icoSizes);
$dir = pack('vvv', 0, 1, count($icoSizes));
$body = '';

foreach ($icoSizes as $i => $size) {
    $data = $icoImages[$i];
    $dir .= pack('CCCCvvVV', $size, $size, 0, 0, 1, 32, strlen($data), $offset);
    $body .= $data;
    $offset += strlen($data);
}

file_put_contents($public.'/favicon.ico', $dir.$body);

// iOS ignores transparency and composites what is left on black, so the touch
// icon has to be opaque whatever the others do.
file_put_contents($public.'/apple-touch-icon.png', $png(180, true));
file_put_contents($public.'/icon-192.png', $png(192, true));
file_put_contents($public.'/icon-512.png', $png(512, true));

// Kept transparent: this one is for the site itself, on whatever it sits on.
file_put_contents($public.'/media/logo.png', $png(512, false));

printf(
    "mark %dx%d in a %d frame\n  favicon.ico          %s (%s)\n  apple-touch-icon.png %s\n  icon-192.png         %s\n  icon-512.png         %s\n  media/logo.png       %s\n",
    $markW, $markH, $frame,
    number_format(filesize($public.'/favicon.ico')).' B',
    implode('/', $icoSizes),
    number_format(filesize($public.'/apple-touch-icon.png')).' B',
    number_format(filesize($public.'/icon-192.png')).' B',
    number_format(filesize($public.'/icon-512.png')).' B',
    number_format(filesize($public.'/media/logo.png')).' B',
);
