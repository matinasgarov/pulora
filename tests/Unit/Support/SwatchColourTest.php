<?php // tests/Unit/Support/SwatchColourTest.php

use App\Support\SwatchColour;

it('resolves a known leather colour name to a hex value', function () {
    expect(SwatchColour::hex('Cognac'))->toBe('#a3612f');
});

it('is case- and whitespace-insensitive', function () {
    expect(SwatchColour::hex(' BLACK '))->toBe('#141210');
});

it('returns null for a name it does not recognise, rather than inventing a colour', function () {
    expect(SwatchColour::hex('Shell cordovan'))->toBeNull();
});

it('returns null for an empty or missing label', function () {
    expect(SwatchColour::hex(null))->toBeNull();
    expect(SwatchColour::hex(''))->toBeNull();
});
