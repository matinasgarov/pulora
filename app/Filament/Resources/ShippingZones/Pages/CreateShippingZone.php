<?php // app/Filament/Resources/ShippingZones/Pages/CreateShippingZone.php

namespace App\Filament\Resources\ShippingZones\Pages;

use App\Filament\Resources\ShippingZones\ShippingZoneResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShippingZone extends CreateRecord
{
    protected static string $resource = ShippingZoneResource::class;
}
