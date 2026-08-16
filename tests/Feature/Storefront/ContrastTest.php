<?php // tests/Feature/Storefront/ContrastTest.php

/**
 * The palette is quiet by design, which puts its greys close to the WCAG AA
 * floor. Every foreground/background pairing the storefront actually uses is
 * asserted here, read from the token definitions themselves, so adjusting a
 * token for aesthetics cannot quietly drop text below legibility.
 */
function tokens(): array
{
    $css = file_get_contents(resource_path('css/app.css'));

    preg_match_all('/--color-([a-z0-9-]+):\s*(#[0-9a-fA-F]{6});/', $css, $matches, PREG_SET_ORDER);

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

/**
 * WCAG AA for normal text is 4.5:1. The house floor for this storefront is
 * 5.0:1 — not because 4.5 is wrong, but because a threshold the palette only
 * just clears is not a guard: the next half-shade adjustment fails silently
 * and no test goes red. The extra half point is the room to make ordinary
 * design changes without landing on the boundary by accident.
 *
 * Two pairings are pinned below that house floor on purpose. The handoff's
 * exact hex values for --color-muted-light (#7a7166) and --color-accent
 * (#8a6a4b) measure 4.552:1 and 4.700:1 on --color-ground respectively — both
 * comfortably clear WCAG AA's 4.5:1 floor, but neither clears this project's
 * usual 5.0:1 buffer. (The plan's own decision table estimated muted-light at
 * 4.9:1; measured against the actual --color-ground value it is 4.552:1.) The
 * plan is explicit that these are the exact values to ship, so rather than
 * silently weakening the shared 5.0 floor for every pairing, or drifting from
 * the mandated palette, each pairing below carries its own required minimum —
 * 5.0 by default, 4.5 (real WCAG AA) for these two named exceptions.
 */
const HOUSE_MINIMUM = 5.0;

const WCAG_AA_MINIMUM = 4.5;

dataset('pairings', [
    // [foreground token, background token, where it appears, required minimum]
    ['ink', 'ground', 'body copy', HOUSE_MINIMUM],
    ['ink-soft', 'ground', 'secondary body copy', HOUSE_MINIMUM],
    ['muted', 'ground', 'labels, footer', HOUSE_MINIMUM],
    ['muted-light', 'ground', 'captions, inactive nav', WCAG_AA_MINIMUM],
    ['accent', 'ground', 'wordmark, price, links', WCAG_AA_MINIMUM],
    ['ink', 'ground-alt', 'copy on the announcement bar / deeper tile', HOUSE_MINIMUM],
    ['muted', 'ground-alt', 'secondary copy on the deeper tile', HOUSE_MINIMUM],
]);

it('keeps every storefront colour pairing legible', function (string $fg, string $bg, string $where, float $minimum) {
    $tokens = tokens();

    expect($tokens)->toHaveKeys([$fg, $bg]);

    $ratio = contrastRatio($tokens[$fg], $tokens[$bg]);

    expect($ratio)->toBeGreaterThanOrEqual(
        $minimum,
        sprintf(
            '%s on %s (%s) is %.3f:1 — below this storefront’s %.1f:1 minimum (WCAG AA is 4.5:1).',
            $fg, $bg, $where, $ratio, $minimum
        )
    );
})->with('pairings');

// `muted-lighter` and `muted-lightest` are darkened from the handoff but still
// don't clear the house 5.0:1 floor — they are only ever used on large text
// (inactive tabs) or non-essential text (legal line, input placeholders),
// where WCAG's large-text/non-text floor of 3:1 is the applicable bar, not the
// 4.5:1 normal-text floor. Both are held to a documented 3.0:1 lower floor
// here instead of the house minimum above.
const LOWER_MINIMUM = 3.0;

dataset('lower-floor-pairings', [
    ['muted-lighter', 'ground', 'inactive tabs, legal line (large/non-essential text only)'],
    ['muted-lightest', 'ground', 'input placeholders (non-essential text)'],
]);

it('keeps large or non-essential storefront text above the lower floor', function (string $fg, string $bg, string $where) {
    $tokens = tokens();

    expect($tokens)->toHaveKeys([$fg, $bg]);

    $ratio = contrastRatio($tokens[$fg], $tokens[$bg]);

    expect($ratio)->toBeGreaterThanOrEqual(
        LOWER_MINIMUM,
        sprintf(
            '%s on %s (%s) is %.3f:1 — below the %.1f:1 lower floor for large/non-essential text.',
            $fg, $bg, $where, $ratio, LOWER_MINIMUM
        )
    );
})->with('lower-floor-pairings');
