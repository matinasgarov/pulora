<?php // tests/Unit/Seeders/WalletImagesSeederTest.php

use Database\Seeders\WalletImagesSeeder;

it('accepts a letter-number product photo name', function (string $name) {
    expect(WalletImagesSeeder::isProductPhoto($name))->toBeTrue();
})->with(['a1', 'K3', 'h4', 'z12', 'a__1', 'D__2']);

it('rejects anything that is not a bare letter-number name', function (string $name) {
    // hero.png landed on the H wallet, and a stray "ChatGPT Image….png" landed
    // on C, because both start with a letter and the old rule only checked
    // that. "h4 (2)" is a genuine duplicate photo, not a naming exception —
    // rejecting it too means a duplicate download doesn't quietly become a
    // fifth angle of a product that has four.
    expect(WalletImagesSeeder::isProductPhoto($name))->toBeFalse();
})->with([
    'hero',
    'ChatGPT Image Aug 17, 2026, 04_36_56 PM',
    'Gemini_Generated_Image_98vmxu98vmxu98vm',
    'h4 (2)',
    '1a',
    'aa1',
]);
