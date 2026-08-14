<?php // app/Support/HasTranslations.php

namespace App\Support;

/**
 * Per-locale content stored as JSON in a single column.
 *
 * Reading a translatable attribute returns a resolved string for the active
 * locale, NOT the raw array. That is deliberate: every existing caller —
 * the Filament tables, CartService::snapshot(), OrderService — reads
 * $product->name expecting a string, and none of them should have to care
 * that the column became bilingual.
 *
 * A model using this trait declares:
 *     protected array $translatable = ['name', 'description'];
 */
trait HasTranslations
{
    public function initializeHasTranslations(): void
    {
        foreach ($this->translatable ?? [] as $attribute) {
            $this->casts[$attribute] = 'array';
        }
    }

    public function getAttributeValue($key)
    {
        $value = parent::getAttributeValue($key);

        if (! in_array($key, $this->translatable ?? [], true)) {
            return $value;
        }

        return $this->resolveTranslation($value);
    }

    /** The raw per-locale array, as stored. */
    public function getTranslations(string $attribute): array
    {
        $value = parent::getAttributeValue($attribute);

        if (is_array($value)) {
            return $value;
        }

        // Content written before this column became translatable, or by a
        // factory that still passes a bare string.
        return $value === null || $value === ''
            ? []
            : [config('app.fallback_locale') => $value];
    }

    public function setTranslation(string $attribute, string $locale, ?string $value): static
    {
        $translations = $this->getTranslations($attribute);
        $translations[$locale] = $value;

        $this->setAttribute($attribute, $translations);

        return $this;
    }

    private function resolveTranslation(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        // Tolerating a bare string is what keeps Plan 1 and 2A's factories green.
        if (! is_array($value)) {
            return (string) $value;
        }

        $locale = app()->getLocale();
        $fallback = config('app.fallback_locale');

        foreach ([$locale, $fallback] as $candidate) {
            if (filled($value[$candidate] ?? null)) {
                return $value[$candidate];
            }
        }

        // Any content at all beats showing the customer nothing.
        foreach ($value as $any) {
            if (filled($any)) {
                return $any;
            }
        }

        return '';
    }
}
