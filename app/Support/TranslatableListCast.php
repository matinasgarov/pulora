<?php // app/Support/TranslatableListCast.php

namespace App\Support;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * Same per-locale JSON storage as HasTranslations — {"en": …, "az": …} — but
 * for a value that is itself an ordered list (Product::$specs), not a
 * scalar string.
 *
 * HasTranslations::resolveTranslation() is typed to return string, so a list
 * value cannot be routed through it without widening that return type for
 * every other translatable column too. Rather than bend the trait, this is a
 * separate cast that mirrors its locale-then-fallback-then-any resolution
 * rule, but for arrays.
 */
class TranslatableListCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($decoded)) {
            return [];
        }

        $locale = app()->getLocale();
        $fallback = config('app.fallback_locale');

        foreach ([$locale, $fallback] as $candidate) {
            if (! empty($decoded[$candidate])) {
                return $decoded[$candidate];
            }
        }

        // Any locale's content beats showing the customer nothing.
        foreach ($decoded as $any) {
            if (! empty($any)) {
                return $any;
            }
        }

        return [];
    }

    public function set($model, string $key, $value, array $attributes): array
    {
        return [$key => json_encode($value ?? [], JSON_UNESCAPED_UNICODE)];
    }
}
