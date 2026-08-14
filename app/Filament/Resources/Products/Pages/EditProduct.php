<?php // app/Filament/Resources/Products/Pages/EditProduct.php

namespace App\Filament\Resources\Products\Pages;

use App\Domain\Catalog\Models\Product;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    /** Translatable columns resolve to a string, so the form uses flat per-locale fields. */
    private const TRANSLATABLE = ['name', 'description', 'story'];

    protected function getHeaderActions(): array
    {
        return [
            // See ProductResource::canDelete(): deleting an ordered product
            // cascades into order history via variants.
            DeleteAction::make()
                ->authorize(fn (Product $record) => ProductResource::canDelete($record)),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (self::TRANSLATABLE as $attribute) {
            $translations = $this->record->getTranslations($attribute);

            foreach (['en', 'az'] as $locale) {
                $data["{$attribute}_{$locale}"] = $translations[$locale] ?? null;
            }

            unset($data[$attribute]);
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        foreach (self::TRANSLATABLE as $attribute) {
            $data[$attribute] = [
                'en' => $data["{$attribute}_en"] ?? null,
                'az' => $data["{$attribute}_az"] ?? null,
            ];

            unset($data["{$attribute}_en"], $data["{$attribute}_az"]);
        }

        return $data;
    }
}
