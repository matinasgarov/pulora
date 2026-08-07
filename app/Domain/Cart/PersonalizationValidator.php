<?php // app/Domain/Cart/PersonalizationValidator.php

namespace App\Domain\Cart;

use App\Domain\Catalog\Models\Product;

class PersonalizationValidator
{
    /**
     * @param  array<string, string>  $input
     * @return array<string, string>  normalized, containing only offered options
     */
    public function validate(Product $product, array $input): array
    {
        $result = [];

        foreach ($product->personalizationOptions as $option) {
            $raw = trim((string) ($input[$option->type] ?? ''));

            if ($raw === '') {
                if ($option->is_required) {
                    throw new InvalidPersonalizationException("{$option->label} is required.");
                }
                continue;
            }

            $value = mb_strtoupper($raw);

            if (mb_strlen($value) > $option->max_characters) {
                throw new InvalidPersonalizationException(
                    "{$option->label} must be at most {$option->max_characters} characters."
                );
            }

            if (preg_match($option->allowed_pattern, $value) !== 1) {
                throw new InvalidPersonalizationException("{$option->label} contains characters we cannot stamp.");
            }

            $result[$option->type] = $value;
        }

        return $result;
    }
}
