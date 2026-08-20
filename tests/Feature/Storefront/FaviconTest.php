<?php // tests/Feature/Storefront/FaviconTest.php

// public/favicon.ico existed as a zero-byte file from the day the project was
// generated, and nothing linked to it. A missing icon is not an error anywhere
// — no request fails, no page breaks — so the only thing that notices is a
// person looking at the tab.
it('ships an icon that is not the empty placeholder', function (string $file, int $atLeast) {
    expect(file_exists(public_path($file)))->toBeTrue("public/$file is missing")
        ->and(filesize(public_path($file)))->toBeGreaterThan($atLeast, "public/$file is empty");
})->with([
    ['favicon.ico', 1000],
    ['apple-touch-icon.png', 1000],
    ['icon-192.png', 1000],
    ['icon-512.png', 1000],
    ['site.webmanifest', 100],
]);

it('holds three sizes in the ico, so the tab strip is not resizing a big one', function () {
    $raw = file_get_contents(public_path('favicon.ico'));

    ['type' => $type, 'count' => $count] = unpack('vreserved/vtype/vcount', substr($raw, 0, 6));

    expect($type)->toBe(1, 'not an icon file')
        ->and($count)->toBe(3);

    $sizes = [];

    for ($i = 0; $i < $count; $i++) {
        $entry = unpack('Cwidth/Cheight', substr($raw, 6 + 16 * $i, 16));
        $sizes[] = $entry['width'];
    }

    expect($sizes)->toBe([16, 32, 48]);
});

it('links the icons from the page head', function () {
    $html = $this->get('/en')->assertOk()->getContent();

    expect($html)->toContain('rel="icon"')
        ->and($html)->toContain('apple-touch-icon.png')
        ->and($html)->toContain('site.webmanifest');
});

// The manifest names the two PNGs; if it points somewhere that does not exist,
// installing the site to a phone home screen gets a blank square.
it('lists icons in the manifest that are actually there', function () {
    $manifest = json_decode(file_get_contents(public_path('site.webmanifest')), true);

    expect($manifest['icons'])->not->toBeEmpty();

    foreach ($manifest['icons'] as $icon) {
        expect(file_exists(public_path(ltrim($icon['src'], '/'))))
            ->toBeTrue($icon['src'].' is listed in the manifest but not on disk');
    }
});
