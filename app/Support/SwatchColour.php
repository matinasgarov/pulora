<?php // app/Support/SwatchColour.php

namespace App\Support;

/**
 * The product page's colour swatches map to real variants (see
 * ProductPurchase / product-purchase.blade.php). A variant's `description`
 * is free-text merchandising copy ("Cognac", "Black") — there is no
 * colour/hex column anywhere in the catalogue schema, and there should not
 * be one invented here either.
 *
 * This is a display-only lookup: a recognised leather-colour name resolves
 * to a representative swatch colour. Anything not recognised returns null,
 * and the caller falls back to a labelled text button instead of guessing a
 * hex for a name nobody vetted.
 */
class SwatchColour
{
    private const MAP = [
        'cognac' => '#a3612f',
        'black' => '#141210',
        'natural' => '#d9c7a8',
        'olive' => '#5f6440',
        'brown' => '#5b3d2b',
        'dark brown' => '#3b2820',
        'tan' => '#c8a06a',
        'chestnut' => '#7b4a30',
        'walnut' => '#5a3d28',
        'chocolate' => '#3f2a1d',
        'camel' => '#c19a6b',
        'navy' => '#1f2937',
        'burgundy' => '#5c1f2e',
        'grey' => '#8a877f',
        'gray' => '#8a877f',
        'white' => '#f2ede3',
        'cream' => '#efe6d3',
        'tobacco' => '#5c4126',
    ];

    public static function hex(?string $label): ?string
    {
        if (! $label || trim($label) === '') {
            return null;
        }

        return self::MAP[mb_strtolower(trim($label))] ?? null;
    }
}
