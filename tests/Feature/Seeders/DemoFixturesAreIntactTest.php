<?php // tests/Feature/Seeders/DemoFixturesAreIntactTest.php

use Illuminate\Support\Facades\File;

/**
 * The photographs in database/demo/card-holders are what a deploy builds the
 * shop from — the originals in walletImages/ are gitignored and never reach a
 * server. They are generated once, by hand, and committed; nothing regenerates
 * or checks them afterwards.
 *
 * One of them, x1.jpg, was written as a solid black rectangle. The normalizer
 * produces a correct image from the same source today and the other 71 files
 * reproduce byte for byte, so it was a one-off failure during the run that
 * made them — silently written, committed, copied into storage on every seed,
 * and served on the live site for two days as a black tile in the grid.
 *
 * Nothing else would have caught it. The file is a valid JPEG of the right
 * size; the seeder wrote it, the page linked it, the browser rendered it. Only
 * a person looking at the grid could tell, which is exactly what happened.
 */
it('has no fixture that is a flat rectangle where a photograph should be', function () {
    $files = File::files(database_path('demo/card-holders'));

    expect($files)->not->toBeEmpty('the deploy fixtures are missing');

    foreach ($files as $file) {
        $image = imagecreatefromjpeg($file->getPathname());
        $w = imagesx($image);
        $h = imagesy($image);

        // The middle of the frame is where the product is: the normalizer
        // centres it and scales it to cover a set fraction of the canvas.
        $samples = [];

        for ($y = (int) ($h * 0.35); $y < (int) ($h * 0.65); $y += 8) {
            for ($x = (int) ($w * 0.35); $x < (int) ($w * 0.65); $x += 8) {
                $rgb = imagecolorat($image, $x, $y);
                $samples[] = ((($rgb >> 16) & 255) + (($rgb >> 8) & 255) + ($rgb & 255)) / 3;
            }
        }

        $mean = array_sum($samples) / count($samples);
        $variance = 0;

        foreach ($samples as $sample) {
            $variance += ($sample - $mean) ** 2;
        }

        $deviation = sqrt($variance / count($samples));

        // Leather photographed at this size always carries grain, stitching or
        // a highlight. The flattest real fixture measures about 5; the black
        // rectangle measured 0.00. Three sits between them with room either
        // side, so this is not tuned to the one file that failed.
        expect($deviation)->toBeGreaterThan(
            3.0,
            $file->getFilename().' is flat — it is a solid block of colour, not a photograph',
        );
    }
});
