<?php // app/Filament/Resources/Products/Pages/CreateProduct.php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /** Translatable columns resolve to a string, so the form uses flat per-locale fields. */
    private const TRANSLATABLE = ['name', 'description', 'story', 'leather'];

    /** `specs` is a translatable list, not a string — its rows go through their own repeater per locale. */
    private const TRANSLATABLE_LISTS = ['specs'];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        foreach (self::TRANSLATABLE as $attribute) {
            $data[$attribute] = [
                'en' => $data["{$attribute}_en"] ?? null,
                'az' => $data["{$attribute}_az"] ?? null,
            ];

            unset($data["{$attribute}_en"], $data["{$attribute}_az"]);
        }

        foreach (self::TRANSLATABLE_LISTS as $attribute) {
            $data[$attribute] = [
                'en' => $data["{$attribute}_en"] ?? [],
                'az' => $data["{$attribute}_az"] ?? [],
            ];

            unset($data["{$attribute}_en"], $data["{$attribute}_az"]);
        }

        return $data;
    }
}
