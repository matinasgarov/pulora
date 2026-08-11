<?php

namespace App\Filament\Resources\Products\Pages;

use App\Domain\Catalog\Models\Product;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // See ProductResource::canDelete(): deleting an ordered product
            // cascades into order history via variants.
            DeleteAction::make()
                ->authorize(fn (Product $record) => ProductResource::canDelete($record)),
        ];
    }
}
