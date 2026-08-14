<?php // app/Filament/Resources/Products/RelationManagers/VariantsRelationManager.php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Domain\Catalog\Models\Variant;
use App\Domain\Money;
use App\Domain\Order\Models\OrderItem;
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

    /** Translatable columns resolve to a string, so the form uses flat per-locale fields. */
    private const TRANSLATABLE = ['description'];

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('sku')->required()->unique(ignoreRecord: true),
            TextInput::make('description_en')
                ->label('Options (English)')
                ->placeholder('Cognac / natural thread'),
            TextInput::make('description_az')
                ->label('Options (Azərbaycan)')
                ->placeholder('Konyak / təbii sap'),
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
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(fn (array $data): array => static::mutateTranslatableDataBeforeSave($data)),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data): array => static::mutateTranslatableDataBeforeFill($data))
                    ->mutateFormDataUsing(fn (array $data): array => static::mutateTranslatableDataBeforeSave($data)),
                // Deleting a variant that's been ordered nulls
                // order_items.variant_id (nullOnDelete), severing that order
                // item from the catalogue and breaking capacity restoration
                // on a later cancel/refund — same reasoning as the product-level
                // guard in ProductResource::canDelete().
                DeleteAction::make()
                    ->authorize(fn (Variant $record) => ! OrderItem::where('variant_id', $record->id)->exists()),
            ]);
    }

    private static function mutateTranslatableDataBeforeFill(array $data): array
    {
        foreach (self::TRANSLATABLE as $attribute) {
            $translations = is_array($data[$attribute] ?? null) ? $data[$attribute] : [];

            foreach (['en', 'az'] as $locale) {
                $data["{$attribute}_{$locale}"] = $translations[$locale] ?? null;
            }

            unset($data[$attribute]);
        }

        return $data;
    }

    private static function mutateTranslatableDataBeforeSave(array $data): array
    {
        foreach (self::TRANSLATABLE as $attribute) {
            $data[$attribute] = [
                'en' => $data["{$attribute}_en"] ?? null,
                'az' => $data["{$attribute}_az"] ?? null,
            ];

            unset($data["{$attribute}_en"], $data["{$attribute}_az"]);
        }

        return $data;
    }
}
