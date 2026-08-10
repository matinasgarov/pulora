<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Domain\Money;
use App\Support\MoneyInput;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('sku')->required()->unique(ignoreRecord: true),
            TextInput::make('description')->placeholder('Cognac / natural thread'),
            MoneyInput::field('price_minor_override')
                ->label('Price override')
                ->helperText('Leave blank to use the product price.'),
            TextInput::make('stock_quantity')
                ->label('Capacity')
                ->helperText('How many of this you are willing to commit to. This is not shelf stock — every piece is made to order.')
                ->numeric()
                ->minValue(0)
                ->default(0),
            TextInput::make('weight_grams')->numeric()->minValue(0)->default(120),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')->searchable(),
                TextColumn::make('description')->label('Options'),
                TextColumn::make('price_minor_override')
                    ->label('Price override')
                    ->formatStateUsing(fn (?int $state) => $state === null ? '—' : Money::format($state)),
                TextInputColumn::make('stock_quantity')
                    ->label('Capacity')
                    ->rules(['integer', 'min:0']),
                IconColumn::make('is_active')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
