<?php // tests/Feature/Storefront/FontSubsetsTest.php

// The per-subset @fontsource files (e.g. latin-ext-400.css) ship a single
// @font-face with NO unicode-range declaration. If app.css imported only that
// file, the browser would use it for every character on the page, but the
// file itself contains no ASCII glyphs — so ordinary Latin letters would
// silently fall back to Georgia/system-ui while ə/ğ/ı/ş rendered in the web
// font. These tests pin down the fix (the aggregate 400.css/500.css
// stylesheets) so a future edit can't reintroduce that trap without a red
// test.

it('imports the aggregate font stylesheets, not a bare subset', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain("@import '@fontsource/bodoni-moda/400.css';")
        ->toContain("@import '@fontsource/bodoni-moda/500.css';")
        ->toContain("@import '@fontsource/archivo/300.css';")
        ->toContain("@import '@fontsource/archivo/400.css';")
        ->toContain("@import '@fontsource/archivo/500.css';")
        ->not->toContain('latin-ext-400.css')
        ->not->toContain('latin-ext-500.css')
        ->not->toContain('latin-ext-300.css');
});

dataset('families', [
    'Bodoni Moda',
    'Archivo',
]);

it('ships built font-face rules whose subsets cover both ASCII and the Azerbaijani schwa', function (string $family) {
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $builtCss = public_path('build/'.$manifest['resources/css/app.css']['file']);

    expect($builtCss)->toBeFile();

    $css = file_get_contents($builtCss);

    preg_match_all(
        '/@font-face\{font-family:[\'"]?'.preg_quote($family, '/').'[\'"]?;[^}]*?unicode-range:([^;}]+)[;}]/',
        $css,
        $matches
    );

    expect($matches[1])->not->toBeEmpty("No {$family} @font-face rules with a unicode-range were found in the built CSS.");

    $coversCodepoint = function (string $rangeList, int $codepoint): bool {
        foreach (explode(',', $rangeList) as $part) {
            $part = trim(str_replace('U+', '', $part));

            if (str_contains($part, '?')) {
                $lo = hexdec(str_replace('?', '0', $part));
                $hi = hexdec(str_replace('?', 'F', $part));
            } elseif (str_contains($part, '-')) {
                [$loHex, $hiHex] = explode('-', $part, 2);
                $lo = hexdec($loHex);
                $hi = hexdec($hiHex);
            } else {
                $lo = $hi = hexdec($part);
            }

            if ($codepoint >= $lo && $codepoint <= $hi) {
                return true;
            }
        }

        return false;
    };

    $asciiA = 0x0041;
    $schwa = 0x0259; // ə

    $rangeCoveringAscii = collect($matches[1])->first(fn ($range) => $coversCodepoint($range, $asciiA));
    $rangeCoveringSchwa = collect($matches[1])->first(fn ($range) => $coversCodepoint($range, $schwa));

    expect($rangeCoveringAscii)->not->toBeNull("No {$family} unicode-range subset covers ASCII \"A\" (U+0041) — English text would fall back to a fallback face.");
    expect($rangeCoveringSchwa)->not->toBeNull("No {$family} unicode-range subset covers the schwa ə (U+0259) — Azerbaijani text would fall back to a fallback face.");

    // Distinct subsets: this is what proves the browser downloads only the
    // woff2 it needs per range, rather than one face silently matching
    // everything because unicode-range was omitted.
    expect($rangeCoveringAscii)->not->toBe($rangeCoveringSchwa);
})->with('families');
