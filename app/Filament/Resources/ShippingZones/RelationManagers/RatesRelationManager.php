<?php // app/Filament/Resources/ShippingZones/RelationManagers/RatesRelationManager.php

namespace App\Filament\Resources\ShippingZones\RelationManagers;

use App\Domain\Money;
use App\Support\MoneyInput;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RatesRelationManager extends RelationManager
{
    protected static string $relationship = 'rates';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->placeholder('Standard'),
            TextInput::make('min_weight_grams')->numeric()->minValue(0)->default(0)->required(),
            TextInput::make('max_weight_grams')
                ->numeric()
                ->minValue(1)
                ->required()
                ->helperText('Gaps or overlaps between brackets are not checked automatically. A parcel heavier than every bracket here gets no shipping quote at all — check coverage across all rates in this zone after adding, editing, or deleting one.'),
            MoneyInput::field('price_minor')
                ->label('Price')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('min_weight_grams')->label('From (g)'),
                TextColumn::make('max_weight_grams')->label('To (g)'),
                TextColumn::make('price_minor')->label('Price')
                    ->formatStateUsing(fn (int $state) => Money::format($state)),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('min_weight_grams');
    }
}
