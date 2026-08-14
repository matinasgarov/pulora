<?php // app/Filament/Resources/DiscountCodes/Pages/EditDiscountCode.php

namespace App\Filament\Resources\DiscountCodes\Pages;

use App\Domain\Discount\Models\DiscountCode;
use App\Filament\Resources\DiscountCodes\DiscountCodeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDiscountCode extends EditRecord
{
    protected static string $resource = DiscountCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // See DiscountCodeResource::canDelete(): deleting a used code
            // destroys the record of why some orders were discounted.
            DeleteAction::make()
                ->authorize(fn (DiscountCode $record) => DiscountCodeResource::canDelete($record)),
        ];
    }
}
