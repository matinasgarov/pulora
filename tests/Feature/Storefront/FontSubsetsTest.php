<?php // tests/Feature/Storefront/FontSubsetsTest.php

// The per-subset @fontsource/eb-garamond files (e.g. latin-ext-400.css) ship
// a single @font-face with NO unicode-range declaration. If app.css imported
// only that file, the browser would use it for every character on the page,
// but the file itself contains no ASCII glyphs — so ordinary Latin letters
// would silently fall back to Georgia while ə/ğ/ı/ş rendered in EB Garamond.
// These tests pin down the fix (the aggregate 400.css/500.css stylesheets)
// so a future edit can't reintroduce that trap without a red test.

it('imports the aggregate font stylesheets, not a bare subset', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain("@import '@fontsource/eb-garamond/400.css';")
        ->toContain("@import '@fontsource/eb-garamond/500.css';")
        ->not->toContain('latin-ext-400.css')
        ->not->toContain('latin-ext-500.css');
});

it('ships built font-face rules whose subsets cover both ASCII and the Azerbaijani schwa', function () {
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $builtCss = public_path('build/'.$manifest['resources/css/app.css']['file']);

    expect($builtCss)->toBeFile();

    $css = file_get_contents($builtCss);

    preg_match_all(
        '/@font-face\{font-family:[\'"]?EB Garamond[\'"]?;[^}]*?unicode-range:([^;}]+)[;}]/',
        $css,
        $matches
    );

    expect($matches[1])->not->toBeEmpty('No EB Garamond @font-face rules with a unicode-range were found in the built CSS.');

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

    expect($rangeCoveringAscii)->not->toBeNull('No unicode-range subset covers ASCII "A" (U+0041) — English text would fall back to Georgia.');
    expect($rangeCoveringSchwa)->not->toBeNull('No unicode-range subset covers the schwa ə (U+0259) — Azerbaijani text would fall back to Georgia.');

    // Distinct subsets: this is what proves the browser downloads only the
    // woff2 it needs per range, rather than one face silently matching
    // everything because unicode-range was omitted.
    expect($rangeCoveringAscii)->not->toBe($rangeCoveringSchwa);
});
