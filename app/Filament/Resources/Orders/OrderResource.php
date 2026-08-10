<?php // app/Filament/Resources/Orders/OrderResource.php

namespace App\Filament\Resources\Orders;

use App\Domain\Money;
use App\Domain\Order\Models\Order;
use App\Domain\Order\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')->searchable()->sortable(),
                TextColumn::make('customer_name')->searchable(),
                TextColumn::make('country_code')->label('To'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state) => $state->label())
                    ->color(fn (OrderStatus $state) => match ($state) {
                        OrderStatus::PendingPayment => 'gray',
                        OrderStatus::Paid => 'warning',
                        OrderStatus::InProduction => 'info',
                        OrderStatus::Shipped, OrderStatus::Delivered => 'success',
                        OrderStatus::Cancelled, OrderStatus::Refunded => 'danger',
                    }),
                TextColumn::make('total_minor')
                    ->label('Total')
                    ->formatStateUsing(fn (int $state) => Money::format($state))
                    ->sortable(),
                TextColumn::make('paid_at')->dateTime('d M Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(
                    collect(OrderStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()
                ),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
