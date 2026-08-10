<?php // app/Filament/Resources/PaymentLogs/Pages/ListPaymentLogs.php

namespace App\Filament\Resources\PaymentLogs\Pages;

use App\Filament\Resources\PaymentLogs\PaymentLogResource;
use Filament\Resources\Pages\ListRecords;

class ListPaymentLogs extends ListRecords
{
    protected static string $resource = PaymentLogResource::class;
}
