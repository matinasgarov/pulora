<?php // app/Filament/Resources/ShippingZones/Pages/EditShippingZone.php

namespace App\Filament\Resources\ShippingZones\Pages;

use App\Domain\Shipping\Models\ShippingZone;
use App\Filament\Resources\ShippingZones\ShippingZoneResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShippingZone extends EditRecord
{
    protected static string $resource = ShippingZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // See ShippingZoneResource::canDelete(): deleting the last
            // fallback zone silently breaks checkout for unlisted countries.
            DeleteAction::make()
                ->authorize(fn (ShippingZone $record) => ShippingZoneResource::canDelete($record)),
        ];
    }
}
