<?php // app/Filament/Resources/PaymentLogs/PaymentLogResource.php

namespace App\Filament\Resources\PaymentLogs;

use App\Domain\Payment\Models\PaymentLog;
use App\Filament\Resources\PaymentLogs\Pages\ListPaymentLogs;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PaymentLogResource extends Resource
{
    protected static ?string $model = PaymentLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('order.order_number')->label('Order')->searchable(),
                TextColumn::make('gateway'),
                TextColumn::make('direction')->badge(),
                TextColumn::make('reference')->searchable()->copyable(),
            ])
            ->filters([
                SelectFilter::make('direction')->options([
                    'request' => 'Request',
                    'callback' => 'Callback',
                    'refund' => 'Refund',
                ]),
            ])
            ->recordActions([
                ViewAction::make()->infolist([
                    KeyValueEntry::make('payload')->label('Raw gateway payload'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentLogs::route('/'),
        ];
    }
}
