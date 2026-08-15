<?php // tests/Feature/Storefront/ContrastTest.php

/**
 * The palette is quiet by design, which puts its greys close to the WCAG AA
 * floor. Muted text was already at 4.89:1 on the ivory ground; moving the
 * announcement bar onto --color-tile silently took the same text to 4.509:1,
 * nine thousandths above failing, with nothing to catch it.
 *
 * Every foreground/background pairing the storefront actually uses is asserted
 * here, read from the token definitions themselves, so adjusting a token for
 * aesthetics cannot quietly drop text below legibility.
 */
function tokens(): array
{
    $css = file_get_contents(resource_path('css/app.css'));

    preg_match_all('/--color-([a-z-]+):\s*(#[0-9a-fA-F]{6});/', $css, $matches, PREG_SET_ORDER);

    return collect($matches)->mapWithKeys(fn ($m) => [$m[1] => $m[2]])->all();
}

function relativeLuminance(string $hex): float
{
    $channels = [];

    foreach ([0, 2, 4] as $offset) {
        $value = hexdec(substr(ltrim($hex, '#'), $offset, 2)) / 255;
        $channels[] = $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }

    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

function contrastRatio(string $foreground, string $background): float
{
    $lighter = max(relativeLuminance($foreground), relativeLuminance($background));
    $darker = min(relativeLuminance($foreground), relativeLuminance($background));

    return ($lighter + 0.05) / ($darker + 0.05);
}

dataset('pairings', [
    // [foreground token, background token, where it appears]
    ['ink', 'ground', 'body copy'],
    ['ink', 'tile', 'product tile copy'],
    ['accent', 'ground', 'wordmark, price, links'],
    ['accent', 'tile', 'accent on a tile'],
    ['muted', 'ground', 'footer, secondary copy'],
    ['muted', 'tile', 'announcement bar'],
]);

/**
 * WCAG AA for normal text is 4.5:1. This asserts 5.0:1.
 *
 * Not because 4.5 is wrong, but because the pairing that prompted this test sat
 * at 4.509 — a margin of nine thousandths. A threshold the palette only just
 * clears is not a guard: the next half-shade adjustment fails silently and no
 * test goes red. The extra half point is the room to make ordinary design
 * changes without landing on the boundary by accident.
 *
 * It also buys back something the ratio alone does not capture: this palette
 * sets its muted text at text-xs with wide letter-spacing in a serif, which is
 * harder to read than the same ratio in body copy.
 */
const HOUSE_MINIMUM = 5.0;

it('keeps every storefront colour pairing legible', function (string $fg, string $bg, string $where) {
    $tokens = tokens();

    expect($tokens)->toHaveKeys([$fg, $bg]);

    $ratio = contrastRatio($tokens[$fg], $tokens[$bg]);

    expect($ratio)->toBeGreaterThanOrEqual(
        HOUSE_MINIMUM,
        sprintf(
            '%s on %s (%s) is %.3f:1 — below this storefront’s %.1f:1 minimum (WCAG AA is 4.5:1).',
            $fg, $bg, $where, $ratio, HOUSE_MINIMUM
        )
    );
})->with('pairings');
