<?php // app/Filament/Resources/ShippingZones/ShippingZoneResource.php

namespace App\Filament\Resources\ShippingZones;

use App\Domain\Shipping\Models\ShippingZone;
use App\Filament\Resources\ShippingZones\Pages\CreateShippingZone;
use App\Filament\Resources\ShippingZones\Pages\EditShippingZone;
use App\Filament\Resources\ShippingZones\Pages\ListShippingZones;
use App\Filament\Resources\ShippingZones\RelationManagers\RatesRelationManager;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShippingZoneResource extends Resource
{
    protected static ?string $model = ShippingZone::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->placeholder('Azerbaijan'),
            TagsInput::make('country_codes')
                ->label('Country codes')
                ->helperText('Two-letter codes, e.g. AZ, GE, TR. Leave empty for a catch-all zone.')
                ->placeholder('AZ'),
            Toggle::make('is_fallback')
                ->label('Use as the fallback zone')
                ->helperText('Orders to countries in no other zone are priced here. At least one zone must always be the fallback, or checkout has no quote for unlisted countries.')
                // ShippingCalculator falls back to the catch-all (is_fallback)
                // zone for any country not covered by another zone. Allowing
                // the last fallback zone to be switched off would silently
                // leave checkout with no quote for those countries.
                ->rule(fn (?ShippingZone $record): Closure => function (string $attribute, $value, Closure $fail) use ($record): void {
                    if ($value) {
                        return;
                    }

                    $anotherFallbackExists = ShippingZone::where('is_fallback', true)
                        ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
                        ->exists();

                    if (! $anotherFallbackExists) {
                        $fail('At least one shipping zone must remain the fallback zone.');
                    }
                }),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('country_codes')->badge(),
                IconColumn::make('is_fallback')->label('Fallback')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            RatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShippingZones::route('/'),
            'create' => CreateShippingZone::route('/create'),
            'edit' => EditShippingZone::route('/{record}/edit'),
        ];
    }
}
