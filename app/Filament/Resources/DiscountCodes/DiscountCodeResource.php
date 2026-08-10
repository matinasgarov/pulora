<?php // app/Filament/Resources/DiscountCodes/DiscountCodeResource.php

namespace App\Filament\Resources\DiscountCodes;

use App\Domain\Discount\Models\DiscountCode;
use App\Domain\Money;
use App\Filament\Resources\DiscountCodes\Pages\CreateDiscountCode;
use App\Filament\Resources\DiscountCodes\Pages\EditDiscountCode;
use App\Filament\Resources\DiscountCodes\Pages\ListDiscountCodes;
use App\Support\MoneyInput;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DiscountCodeResource extends Resource
{
    protected static ?string $model = DiscountCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->required()
                ->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn (string $state) => strtoupper(trim($state))),

            Select::make('kind')
                ->options(['percent' => 'Percentage off', 'fixed' => 'Fixed amount off'])
                ->default('percent')
                ->required()
                ->live(),

            // A percent is a plain integer; a fixed amount is money and gets the
            // same string-parsed conversion as every other price in the panel.
            TextInput::make('value')
                ->label(fn (Get $get) => $get('kind') === 'fixed' ? 'Amount' : 'Percent')
                ->prefix(fn (Get $get) => $get('kind') === 'fixed' ? 'AZN' : null)
                ->suffix(fn (Get $get) => $get('kind') === 'percent' ? '%' : null)
                ->required()
                ->rule(fn (Get $get) => $get('kind') === 'fixed'
                    ? 'regex:/^\d+([.,]\d{1,2})?$/'
                    : 'integer')
                ->minValue(1)
                ->maxValue(fn (Get $get) => $get('kind') === 'percent' ? 100 : null)
                ->formatStateUsing(fn (?int $state, Get $get) => $get('kind') === 'fixed'
                    ? MoneyInput::toManats($state)
                    : $state)
                ->dehydrateStateUsing(fn (?string $state, Get $get) => $get('kind') === 'fixed'
                    ? MoneyInput::toMinor($state)
                    : (int) $state),

            MoneyInput::field('minimum_order_minor')
                ->label('Minimum order')
                ->default(0)
                ->dehydrateStateUsing(fn (?string $state) => MoneyInput::toMinor($state) ?? 0),

            TextInput::make('usage_limit')
                ->numeric()
                ->minValue(1)
                ->helperText('Leave blank for unlimited use.'),

            DateTimePicker::make('expires_at'),

            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable(),
                TextColumn::make('kind')->badge(),
                TextColumn::make('value')
                    ->formatStateUsing(fn (int $state, DiscountCode $record) => $record->kind === 'fixed'
                        ? Money::format($state)
                        : "{$state}%"),
                // Read-only: times_used belongs to DiscountService::consume()'s
                // conditional atomic increment. A form that could write it would
                // reintroduce the usage-limit race Plan 1 closed.
                TextColumn::make('times_used')->label('Used'),
                TextColumn::make('usage_limit')->label('Limit')->placeholder('Unlimited'),
                TextColumn::make('expires_at')->dateTime('d M Y')->placeholder('Never'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('code');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDiscountCodes::route('/'),
            'create' => CreateDiscountCode::route('/create'),
            'edit' => EditDiscountCode::route('/{record}/edit'),
        ];
    }
}
