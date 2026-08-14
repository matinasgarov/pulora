<?php // app/Filament/Resources/Orders/Pages/ViewOrder.php

namespace App\Filament\Resources\Orders\Pages;

use App\Domain\Money;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Order\OrderStatus;
use App\Filament\Actions\TransitionActions;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TransitionActions::startProduction(),
            TransitionActions::markReady(),
            TransitionActions::ship(),
            TransitionActions::deliver(),
            TransitionActions::cancel(),
            TransitionActions::refund(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Order')
                ->schema([
                    TextEntry::make('order_number')->label('Order number'),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (OrderStatus $state) => $state->label()),
                    TextEntry::make('customer_name')->label('Customer'),
                    TextEntry::make('customer_email')->label('Email'),
                    TextEntry::make('phone')->placeholder('—'),
                    TextEntry::make('address_line1')->label('Address'),
                    TextEntry::make('address_line2')->label('Address (cont.)')->placeholder('—'),
                    TextEntry::make('city'),
                    TextEntry::make('postcode')->placeholder('—'),
                    TextEntry::make('country_code')->label('Country'),
                    TextEntry::make('total_minor')->label('Total')->formatStateUsing(fn (int $state) => Money::format($state)),
                ])
                ->columns(3),

            // Snapshot data as it was captured at order time — never re-joined
            // to the live catalogue.
            Section::make('Items')
                ->schema([
                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('product_name')->label('Product'),
                            TextEntry::make('variant_description')->label('Variant'),
                            TextEntry::make('quantity'),
                            TextEntry::make('unit_price_minor')
                                ->label('Unit price')
                                ->formatStateUsing(fn (int $state) => Money::format($state)),
                            // Filament flattens an array state and renders each
                            // value separately, which drops the keys: an operator
                            // seeing "MA, yes" cannot tell the monogram from the
                            // gift wrap, and cuts the wrong thing. Joining to one
                            // string up front keeps every label attached to its
                            // value, matching the workshop card's headline style.
                            TextEntry::make('personalization')
                                ->label('Personalization')
                                ->state(fn (OrderItem $record) => filled($record->personalization)
                                    ? collect($record->personalization)
                                        ->map(fn ($value, $key) => str($key)->headline().': '.$value)
                                        ->implode(', ')
                                    : '—'),
                        ])
                        ->columns(5),
                ]),

            Section::make('Payment logs')
                ->schema([
                    RepeatableEntry::make('paymentLogs')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('created_at')->dateTime('d M Y H:i'),
                            TextEntry::make('gateway'),
                            TextEntry::make('direction')->badge(),
                            TextEntry::make('reference'),
                        ])
                        ->columns(4),
                ])
                ->collapsible(),

            Section::make('History')
                ->schema([
                    RepeatableEntry::make('events')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('created_at')->dateTime('d M Y H:i'),
                            TextEntry::make('from_status')->label('From'),
                            TextEntry::make('to_status')->label('To'),
                            TextEntry::make('note')->placeholder('—'),
                        ])
                        ->columns(4),
                ]),
        ]);
    }
}
