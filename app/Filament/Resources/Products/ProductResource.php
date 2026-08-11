<?php

namespace App\Filament\Resources\Products;

use App\Domain\Catalog\Models\Product;
use BackedEnum;
use App\Domain\Money;
use App\Domain\Order\Models\OrderItem;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Support\MoneyInput;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->tabs([
                Tabs\Tab::make('Details')->schema([
                    TextInput::make('name')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', Str::slug($state ?? ''))),

                    TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        // A slug change on a product someone has bought is a dead
                        // link and a lost sale.
                        ->disabled(fn (?Product $record) => $record !== null && static::hasBeenOrdered($record))
                        ->helperText(fn (?Product $record) => $record !== null && static::hasBeenOrdered($record)
                            ? 'Locked: this product has been ordered.'
                            : null),

                    Textarea::make('description')->rows(4),
                    Textarea::make('story')->rows(4),

                    MoneyInput::field('base_price_minor')
                        ->label('Price')
                        ->required(),

                    TextInput::make('lead_time_days')->numeric()->minValue(0)->default(3),
                    Toggle::make('is_active')->default(true),
                ]),

                Tabs\Tab::make('Images')->schema([
                    Repeater::make('images')
                        ->relationship()
                        ->schema([
                            FileUpload::make('path')
                                ->image()
                                ->disk('public')
                                ->directory('products')
                                ->required(),
                            TextInput::make('alt_text'),
                        ])
                        ->orderColumn('sort_order')
                        ->reorderable()
                        // Filament repeaters default to one blank item; without this
                        // a fresh product form fails validation on fields nobody touched.
                        ->defaultItems(0),
                ]),

                Tabs\Tab::make('Options')->schema([
                    Repeater::make('variantOptions')
                        ->relationship()
                        ->schema([
                            TextInput::make('name')->required()->placeholder('Leather colour'),
                            Repeater::make('values')
                                ->relationship()
                                ->schema([TextInput::make('value')->required()])
                                ->orderColumn('sort_order')
                                ->defaultItems(0),
                        ])
                        ->orderColumn('sort_order')
                        ->defaultItems(0),

                    Repeater::make('personalizationOptions')
                        ->relationship()
                        ->schema([
                            Select::make('type')->options([
                                'monogram' => 'Monogram',
                                'gift_wrap' => 'Gift wrap',
                                'custom_stamp' => 'Custom stamp',
                            ])->required(),
                            TextInput::make('label')->required(),
                            MoneyInput::field('price_delta_minor')
                                ->label('Extra charge')
                                ->default(0)
                                ->dehydrateStateUsing(fn (?string $state) => MoneyInput::toMinor($state) ?? 0),
                            TextInput::make('max_characters')->numeric()->default(3),
                            TextInput::make('allowed_pattern')->default('/^[A-Z]+$/'),
                            Toggle::make('is_required'),
                        ])
                        ->defaultItems(0),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    /** A product is locked once any order item points at one of its variants. */
    private static function hasBeenOrdered(Product $product): bool
    {
        return OrderItem::whereIn('variant_id', $product->variants()->pluck('id'))->exists();
    }

    // Deleting an ordered product cascades to its variants (variants.product_id
    // is cascadeOnDelete), which in turn nulls order_items.variant_id
    // (nullOnDelete) for every historical order — permanently severing those
    // order items from the catalogue and silently breaking
    // OrderService::restoreCapacity() (it skips null variant_id) for any later
    // cancel/refund on those orders. Retiring via is_active is the correct
    // mechanism once a product has been ordered; deletion is strictly worse
    // than the slug lock above and must be blocked the same way.
    public static function canDelete(Model $record): bool
    {
        return ! static::hasBeenOrdered($record);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('variants_count')->counts('variants')->label('Variants'),
                TextColumn::make('base_price_minor')
                    ->label('Price')
                    ->formatStateUsing(fn (int $state) => Money::format($state))
                    ->sortable(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([TernaryFilter::make('is_active')->label('Active')])
            ->recordActions([EditAction::make()])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            VariantsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
