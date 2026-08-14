# Bilingual Storefront (Plan 2B) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the shop a customer-facing storefront in Azerbaijani and English, so someone can find a product, choose its options, and buy it.

**Architecture:** The commerce engine (cart, shipping, discounts, orders, payment) and the operator's admin are both complete and tested. This plan adds only a presentation layer over them: translatable content, locale routing, a design system, and the buy path. Browsable pages are plain controllers and Blade; Livewire is reserved for the three genuinely stateful islands. No domain decision is ever re-derived in a view.

**Tech Stack:** Laravel 12, PHP 8.5.4, Filament v5.7.6, Livewire 4, Tailwind 4 + Vite, Pest 4 / PHPUnit 12, SQLite in-memory for tests, MySQL 8 in production.

**Spec:** `docs/superpowers/specs/2026-08-14-storefront-design.md`

## Global Constraints

Copied from the spec. Every task's requirements implicitly include this section.

- **Money is always an integer in minor units (qəpik).** Never float, never decimal. Display goes through `App\Support\Money::format()`; entry goes through `App\Support\MoneyInput`.
- **Currency is AZN only.**
- **`order_items` are snapshots.** Never joined live to the catalogue for display.
- **`variants.stock_quantity` is a capacity cap** the operator sets by hand, not shelf inventory.
- **Guest checkout only.** No customer accounts. The `users` table holds operators.
- **No code path writes `orders.status` directly.** Every change goes through `OrderService::transition()`.
- **The storefront never re-implements a domain decision.** Prices, shipping quotes, discount validity and personalization rules are resolved by the existing services; views render their answers.
- **One write path per operation.** Where two entry points need the same behaviour, they call one service.
- **Default and fallback locale are both `en`.** `/` redirects to `/en`. Supported locales are exactly `en` and `az`.
- **Filament's own interface stays English.** Only content entry becomes per-locale.
- Composer is invoked as `php C:/php/composer.phar`. Tests run with `php artisan test`.
- Baseline suite before this plan: **175 passed, 2 skipped** on SQLite. The 2 skips are the concurrency tests, which only run on MySQL. It must stay green.

## Facts established by reading the code (do not re-derive)

- `CartService::add(int $variantId, int $quantity, array $personalization = [])` already validates personalization against the product's own options and throws `InvalidQuantityException` on `$quantity < 1`.
- `CartService::snapshot()` **already folds `price_delta_minor` into `CartLine::$unitPriceMinor`** via a private `personalizationDeltaMinor()`. Never add the delta again in a view.
- `CartService::snapshot()` **silently drops** any line whose variant or product is inactive (a bare `continue`). Task 7 surfaces this to the customer.
- `Variant::effectivePriceMinor()` exists and resolves `price_minor_override ?? product->base_price_minor`.
- `ShippingCalculator::quotesFor(string $countryCode, int $weightGrams): array` returns `ShippingQuote` objects; `quoteById(int $rateId, string $countryCode, int $weightGrams): ShippingQuote` throws `NoShippingRateException`.
- `CartLine` is a readonly-style value object with public `lineKey, variantId, quantity, productName, variantDescription, unitPriceMinor, personalization, weightGrams` and a `lineTotalMinor()` method.
- Existing customer routes to keep: `POST /checkout`, `POST /payment/callback`, `GET /checkout/confirmation`, `GET|POST /orders/lookup`.

---

## File Structure

**Task 1 — Translatable content foundation**
- Create: `app/Support/HasTranslations.php`
- Create: `database/migrations/2026_08_14_000100_make_catalog_content_translatable.php`
- Create: `database/migrations/2026_08_14_000200_add_locale_to_orders.php`
- Modify: the six catalogue models to declare `$translatable`
- Test: `tests/Unit/Support/HasTranslationsTest.php`, `tests/Feature/Catalog/TranslatableContentTest.php`

**Task 2 — Per-locale content entry in the admin**
- Modify: `app/Filament/Resources/Products/ProductResource.php`, its `Pages/CreateProduct.php` and `Pages/EditProduct.php`, `RelationManagers/VariantsRelationManager.php`
- Test: `tests/Feature/Admin/ProductTranslationTest.php`

**Task 3 — Locale routing, switcher, translation files**
- Create: `app/Http/Middleware/SetLocale.php`, `lang/en/shop.php`, `lang/az/shop.php`
- Modify: `bootstrap/app.php`, `routes/web.php`
- Test: `tests/Feature/Storefront/LocaleRoutingTest.php`

**Task 4 — Design system and layout shell**
- Modify: `resources/css/app.css`, `package.json`
- Create: `resources/views/components/layouts/storefront.blade.php`, `resources/views/components/site-header.blade.php`, `resources/views/components/site-footer.blade.php`, `resources/views/components/price.blade.php`
- Test: `tests/Feature/Storefront/LayoutTest.php`

**Task 5 — Catalogue**
- Create: `app/Http/Controllers/Storefront/CatalogueController.php`, `resources/views/storefront/catalogue.blade.php`, `resources/views/components/product-tile.blade.php`
- Test: `tests/Feature/Storefront/CatalogueTest.php`

**Task 6 — Product detail and purchase island**
- Create: `app/Http/Controllers/Storefront/ProductController.php`, `app/Livewire/ProductPurchase.php`, `resources/views/storefront/product.blade.php`, `resources/views/livewire/product-purchase.blade.php`
- Test: `tests/Feature/Storefront/ProductPageTest.php`, `tests/Feature/Livewire/ProductPurchaseTest.php`

**Task 7 — Cart**
- Create: `app/Livewire/CartPage.php`, `app/Livewire/CartCount.php`, `resources/views/livewire/cart-page.blade.php`, `resources/views/livewire/cart-count.blade.php`
- Test: `tests/Feature/Livewire/CartPageTest.php`

**Task 8 — PlaceOrder extraction and checkout**
- Create: `app/Domain/Checkout/PlaceOrder.php`, `app/Livewire/CheckoutForm.php`, `resources/views/livewire/checkout-form.blade.php`
- Modify: `app/Http/Controllers/CheckoutController.php`
- Test: `tests/Feature/Livewire/CheckoutFormTest.php`

**Task 9 — Confirmation, lookup, order locale**
- Modify: `resources/views/checkout/confirmation.blade.php`, `resources/views/orders/lookup.blade.php`, `resources/views/orders/show.blade.php`, `app/Domain/Checkout/PlaceOrder.php`
- Test: `tests/Feature/Storefront/OrderLocaleTest.php`

---

## Task 1: Translatable content foundation

**Files:**
- Create: `app/Support/HasTranslations.php`
- Create: `database/migrations/2026_08_14_000100_make_catalog_content_translatable.php`
- Create: `database/migrations/2026_08_14_000200_add_locale_to_orders.php`
- Modify: `app/Domain/Catalog/Models/Product.php`, `Variant.php`, `ProductImage.php`, `VariantOption.php`, `OptionValue.php`, `PersonalizationOption.php`
- Test: `tests/Unit/Support/HasTranslationsTest.php`, `tests/Feature/Catalog/TranslatableContentTest.php`

**Interfaces:**
- Produces:
  - `App\Support\HasTranslations` trait. A model using it declares `protected array $translatable = ['name', ...]`.
  - Reading a translatable attribute (`$product->name`) returns a **resolved string** for the active locale, never an array. This is what keeps every existing caller — the admin tables, `CartService::snapshot()`, `OrderService` — working unchanged.
  - `getTranslations(string $attribute): array` returns the raw per-locale array.
  - `setTranslation(string $attribute, string $locale, ?string $value): static`.
  - `orders.locale` column, nullable string, default `null`.

**Critical design note for the implementer:** the trait must be *tolerant of plain strings*. Every existing test and factory writes `['name' => 'Bifold wallet']` as a bare string. If resolution throws or returns null on a string, roughly forty existing tests break. Resolution returns a bare string unchanged.

- [ ] **Step 1: Write the failing unit test**

Create `tests/Unit/Support/HasTranslationsTest.php`:

```php
<?php // tests/Unit/Support/HasTranslationsTest.php

use App\Domain\Catalog\Models\Product;

it('returns the value for the active locale', function () {
    $product = new Product(['name' => ['en' => 'Bifold wallet', 'az' => 'İkiqat pulqabı']]);

    app()->setLocale('en');
    expect($product->name)->toBe('Bifold wallet');

    app()->setLocale('az');
    expect($product->name)->toBe('İkiqat pulqabı');
});

it('falls back to the default locale when the active one is empty', function () {
    $product = new Product(['name' => ['en' => 'Bifold wallet', 'az' => '']]);

    app()->setLocale('az');

    expect($product->name)->toBe('Bifold wallet');
});

it('falls back when the active locale key is missing entirely', function () {
    $product = new Product(['name' => ['en' => 'Bifold wallet']]);

    app()->setLocale('az');

    expect($product->name)->toBe('Bifold wallet');
});

// Every pre-existing factory and test writes a bare string. If this breaks,
// roughly forty tests from Plan 1 and Plan 2A break with it.
it('passes a plain string through untouched', function () {
    $product = new Product(['name' => 'Bifold wallet']);

    app()->setLocale('az');

    expect($product->name)->toBe('Bifold wallet');
});

it('returns an empty string rather than null when nothing is set', function () {
    $product = new Product;

    expect($product->name)->toBe('');
});

it('exposes the raw per-locale array', function () {
    $product = new Product(['name' => ['en' => 'Bifold wallet', 'az' => 'İkiqat pulqabı']]);

    expect($product->getTranslations('name'))
        ->toBe(['en' => 'Bifold wallet', 'az' => 'İkiqat pulqabı']);
});

it('wraps a plain string when the raw array is requested', function () {
    $product = new Product(['name' => 'Bifold wallet']);

    expect($product->getTranslations('name'))->toBe(['en' => 'Bifold wallet']);
});

it('sets a single locale without disturbing the other', function () {
    $product = new Product(['name' => ['en' => 'Bifold wallet']]);

    $product->setTranslation('name', 'az', 'İkiqat pulqabı');

    expect($product->getTranslations('name'))
        ->toBe(['en' => 'Bifold wallet', 'az' => 'İkiqat pulqabı']);
});

it('leaves non-translatable attributes alone', function () {
    $product = new Product(['slug' => 'bifold-wallet']);

    expect($product->slug)->toBe('bifold-wallet');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=HasTranslationsTest`
Expected: FAIL — `Call to undefined method ... getTranslations()`.

- [ ] **Step 3: Write the trait**

Create `app/Support/HasTranslations.php`:

```php
<?php // app/Support/HasTranslations.php

namespace App\Support;

/**
 * Per-locale content stored as JSON in a single column.
 *
 * Reading a translatable attribute returns a resolved string for the active
 * locale, NOT the raw array. That is deliberate: every existing caller —
 * the Filament tables, CartService::snapshot(), OrderService — reads
 * $product->name expecting a string, and none of them should have to care
 * that the column became bilingual.
 *
 * A model using this trait declares:
 *     protected array $translatable = ['name', 'description'];
 */
trait HasTranslations
{
    public function initializeHasTranslations(): void
    {
        foreach ($this->translatable ?? [] as $attribute) {
            $this->casts[$attribute] = 'array';
        }
    }

    public function getAttributeValue($key)
    {
        $value = parent::getAttributeValue($key);

        if (! in_array($key, $this->translatable ?? [], true)) {
            return $value;
        }

        return $this->resolveTranslation($value);
    }

    /** The raw per-locale array, as stored. */
    public function getTranslations(string $attribute): array
    {
        $value = parent::getAttributeValue($attribute);

        if (is_array($value)) {
            return $value;
        }

        // Content written before this column became translatable, or by a
        // factory that still passes a bare string.
        return $value === null || $value === ''
            ? []
            : [config('app.fallback_locale') => $value];
    }

    public function setTranslation(string $attribute, string $locale, ?string $value): static
    {
        $translations = $this->getTranslations($attribute);
        $translations[$locale] = $value;

        $this->setAttribute($attribute, $translations);

        return $this;
    }

    private function resolveTranslation(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        // Tolerating a bare string is what keeps Plan 1 and 2A's factories green.
        if (! is_array($value)) {
            return (string) $value;
        }

        $locale = app()->getLocale();
        $fallback = config('app.fallback_locale');

        foreach ([$locale, $fallback] as $candidate) {
            if (filled($value[$candidate] ?? null)) {
                return $value[$candidate];
            }
        }

        // Any content at all beats showing the customer nothing.
        foreach ($value as $any) {
            if (filled($any)) {
                return $any;
            }
        }

        return '';
    }
}
```

- [ ] **Step 4: Add the trait to the six catalogue models**

In each model add `use App\Support\HasTranslations;` to the imports, `use HasTranslations;` inside the class body alongside the existing traits, and the `$translatable` property:

- `app/Domain/Catalog/Models/Product.php` → `protected array $translatable = ['name', 'description', 'story'];`
- `app/Domain/Catalog/Models/Variant.php` → `protected array $translatable = ['description'];`
- `app/Domain/Catalog/Models/ProductImage.php` → `protected array $translatable = ['alt_text'];`
- `app/Domain/Catalog/Models/VariantOption.php` → `protected array $translatable = ['name'];`
- `app/Domain/Catalog/Models/OptionValue.php` → `protected array $translatable = ['value'];`
- `app/Domain/Catalog/Models/PersonalizationOption.php` → `protected array $translatable = ['label'];`

- [ ] **Step 5: Run the unit test to verify it passes**

Run: `php artisan test --filter=HasTranslationsTest`
Expected: PASS, 9 tests.

- [ ] **Step 6: Write the migration**

The columns stay text — Laravel's `array` cast serialises JSON into a text column perfectly well, and avoiding a type change avoids SQLite's table-rebuild path. What *does* change is width: `products.name` and friends are `string` (255), and two locales of a long name will overflow that.

Create `database/migrations/2026_08_14_000100_make_catalog_content_translatable.php`:

```php
<?php // database/migrations/2026_08_14_000100_make_catalog_content_translatable.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** table => [columns that become translatable] */
    private const TARGETS = [
        'products' => ['name', 'description', 'story'],
        'variants' => ['description'],
        'product_images' => ['alt_text'],
        'variant_options' => ['name'],
        'option_values' => ['value'],
        'personalization_options' => ['label'],
    ];

    /** Columns declared as string(255); two locales will not fit. */
    private const WIDEN = [
        'products' => ['name'],
        'variants' => ['description'],
        'product_images' => ['alt_text'],
        'variant_options' => ['name'],
        'option_values' => ['value'],
        'personalization_options' => ['label'],
    ];

    public function up(): void
    {
        foreach (self::WIDEN as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $column) {
                    $t->text($column)->nullable()->change();
                }
            });
        }

        foreach (self::TARGETS as $table => $columns) {
            foreach ($columns as $column) {
                $this->wrap($table, $column);
            }
        }
    }

    public function down(): void
    {
        foreach (self::TARGETS as $table => $columns) {
            foreach ($columns as $column) {
                $this->unwrap($table, $column);
            }
        }
    }

    /** 'Bifold wallet' becomes {"en":"Bifold wallet"}. */
    private function wrap(string $table, string $column): void
    {
        DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, $column) {
            foreach ($rows as $row) {
                $value = $row->{$column};

                if ($value === null || $value === '') {
                    continue;
                }

                // Idempotent: a value that is already a JSON object is left alone.
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    continue;
                }

                DB::table($table)->where('id', $row->id)->update([
                    $column => json_encode(
                        [config('app.fallback_locale') => $value],
                        JSON_UNESCAPED_UNICODE
                    ),
                ]);
            }
        });
    }

    /** {"en":"Bifold wallet"} becomes 'Bifold wallet'. */
    private function unwrap(string $table, string $column): void
    {
        DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, $column) {
            foreach ($rows as $row) {
                $decoded = json_decode((string) $row->{$column}, true);

                if (! is_array($decoded)) {
                    continue;
                }

                DB::table($table)->where('id', $row->id)->update([
                    $column => $decoded[config('app.fallback_locale')] ?? reset($decoded) ?: '',
                ]);
            }
        });
    }
};
```

Create `database/migrations/2026_08_14_000200_add_locale_to_orders.php`:

```php
<?php // database/migrations/2026_08_14_000200_add_locale_to_orders.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            // The language the customer actually bought in. Drives which
            // language confirmation and shipment emails go out in.
            $t->string('locale', 5)->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropColumn('locale');
        });
    }
};
```

- [ ] **Step 7: Write the integration test**

Create `tests/Feature/Catalog/TranslatableContentTest.php`:

```php
<?php // tests/Feature/Catalog/TranslatableContentTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;

it('round-trips both locales through the database', function () {
    $product = Product::factory()->create([
        'name' => ['en' => 'Bifold wallet', 'az' => 'İkiqat pulqabı'],
    ]);

    $fresh = Product::find($product->id);

    app()->setLocale('az');
    expect($fresh->name)->toBe('İkiqat pulqabı');

    app()->setLocale('en');
    expect($fresh->name)->toBe('Bifold wallet');
});

it('keeps a variant description translatable', function () {
    $variant = Variant::factory()->for(Product::factory())->create([
        'description' => ['en' => 'Cognac / natural thread', 'az' => 'Konyak / təbii sap'],
    ]);

    app()->setLocale('az');

    expect(Variant::find($variant->id)->description)->toBe('Konyak / təbii sap');
});

it('still accepts a plain string from an older factory', function () {
    $product = Product::factory()->create(['name' => 'Card holder']);

    app()->setLocale('az');

    expect(Product::find($product->id)->name)->toBe('Card holder');
});

it('records nothing on orders.locale by default', function () {
    expect(Schema::hasColumn('orders', 'locale'))->toBeTrue();
});
```

Add `use Illuminate\Support\Facades\Schema;` to that file's imports.

- [ ] **Step 8: Run the tests**

Run: `php artisan test --filter="HasTranslationsTest|TranslatableContentTest"`
Expected: PASS, 13 tests.

- [ ] **Step 9: Run the full suite**

Run: `php artisan test`
Expected: 175 passed, 2 skipped, plus the 13 new ones — **188 passed, 2 skipped**. If anything from Plan 1 or 2A broke, the trait is not tolerating plain strings; fix that rather than editing the old tests.

- [ ] **Step 10: Commit**

```bash
git add app/Support/HasTranslations.php app/Domain/Catalog/Models database/migrations tests/Unit/Support/HasTranslationsTest.php tests/Feature/Catalog/TranslatableContentTest.php
git commit -m "feat: make catalogue content translatable"
```

---

## Task 2: Per-locale content entry in the admin

Filament's own interface stays English. Only the content fields change.

**Files:**
- Modify: `app/Filament/Resources/Products/ProductResource.php`
- Modify: `app/Filament/Resources/Products/Pages/CreateProduct.php`, `Pages/EditProduct.php`
- Modify: `app/Filament/Resources/Products/RelationManagers/VariantsRelationManager.php`
- Test: `tests/Feature/Admin/ProductTranslationTest.php`

**Interfaces:**
- Consumes: `HasTranslations::getTranslations()` / `setTranslation()` from Task 1.
- Produces: nothing later tasks depend on.

**Approach:** flat per-locale form fields (`name_en`, `name_az`) mapped in and out with Filament's `mutateFormDataBeforeFill()` and `mutateFormDataBeforeSave()`. This is deliberately explicit rather than clever: `$product->name` resolves to a string, so binding a form field straight to `name.az` would fight the trait.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/ProductTranslationTest.php`:

```php
<?php // tests/Feature/Admin/ProductTranslationTest.php

use App\Domain\Catalog\Models\Product;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_operator' => true]));
});

it('stores both locales from the create form', function () {
    livewire(CreateProduct::class)
        ->fillForm([
            'name_en' => 'Card holder',
            'name_az' => 'Kart qabı',
            'slug' => 'card-holder',
            'base_price_minor' => '49.99',
            'lead_time_days' => 3,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::where('slug', 'card-holder')->sole();

    expect($product->getTranslations('name'))
        ->toBe(['en' => 'Card holder', 'az' => 'Kart qabı']);
});

it('fills the edit form from both locales', function () {
    $product = Product::factory()->create([
        'name' => ['en' => 'Bifold wallet', 'az' => 'İkiqat pulqabı'],
    ]);

    livewire(EditProduct::class, ['record' => $product->getKey()])
        ->assertFormSet([
            'name_en' => 'Bifold wallet',
            'name_az' => 'İkiqat pulqabı',
        ]);
});

it('requires the default locale but allows the other to be blank', function () {
    livewire(CreateProduct::class)
        ->fillForm([
            'name_en' => '',
            'name_az' => 'Kart qabı',
            'slug' => 'x',
            'base_price_minor' => '10.00',
        ])
        ->call('create')
        ->assertHasFormErrors(['name_en']);
});

it('saves an English-only product without complaining', function () {
    livewire(CreateProduct::class)
        ->fillForm([
            'name_en' => 'Belt',
            'name_az' => '',
            'slug' => 'belt',
            'base_price_minor' => '30.00',
            'lead_time_days' => 3,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Product::where('slug', 'belt')->sole()->getTranslations('name')['en'])->toBe('Belt');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=ProductTranslationTest`
Expected: FAIL — the form has no `name_en` field.

- [ ] **Step 3: Replace the translatable fields in the product form**

In `ProductResource::form()`, replace the single `name`, `description` and `story` inputs inside the Details tab with per-locale pairs. Keep every other field, and keep the slug lock exactly as it is:

```php
                Tabs\Tab::make('Details')->schema([
                    TextInput::make('name_en')
                        ->label('Name (English)')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', Str::slug($state ?? ''))),

                    TextInput::make('name_az')
                        ->label('Name (Azərbaycan)')
                        ->helperText('Leave blank to fall back to English.'),

                    TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        // A slug change on a product someone has bought is a dead
                        // link and a lost sale.
                        ->disabled(fn (?Product $record) => $record !== null && static::hasBeenOrdered($record))
                        ->helperText(fn (?Product $record) => $record !== null && static::hasBeenOrdered($record)
                            ? 'Locked: this product has been ordered.'
                            : null),

                    Textarea::make('description_en')->label('Description (English)')->rows(4),
                    Textarea::make('description_az')->label('Description (Azərbaycan)')->rows(4),

                    Textarea::make('story_en')->label('Story (English)')->rows(4),
                    Textarea::make('story_az')->label('Story (Azərbaycan)')->rows(4),

                    MoneyInput::field('base_price_minor')
                        ->label('Price')
                        ->required(),

                    TextInput::make('lead_time_days')->numeric()->minValue(0)->default(3),
                    Toggle::make('is_active')->default(true),
                ]),
```

Note the slug is still derived from the English name only — it is a single-language URL.

- [ ] **Step 4: Map the fields in and out on both pages**

Add to `app/Filament/Resources/Products/Pages/EditProduct.php`:

```php
    /** Translatable columns resolve to a string, so the form uses flat per-locale fields. */
    private const TRANSLATABLE = ['name', 'description', 'story'];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (self::TRANSLATABLE as $attribute) {
            $translations = $this->record->getTranslations($attribute);

            foreach (['en', 'az'] as $locale) {
                $data["{$attribute}_{$locale}"] = $translations[$locale] ?? null;
            }

            unset($data[$attribute]);
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
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
```

Add the same `TRANSLATABLE` constant and an identical `mutateFormDataBeforeCreate()` to `app/Filament/Resources/Products/Pages/CreateProduct.php` — the create page has no `$this->record` to fill from, so it needs only the save direction:

```php
    private const TRANSLATABLE = ['name', 'description', 'story'];

    protected function mutateFormDataBeforeCreate(array $data): array
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
```

- [ ] **Step 5: Do the same for the variant description**

In `RelationManagers/VariantsRelationManager.php`, replace the single `description` input with:

```php
            TextInput::make('description_en')
                ->label('Options (English)')
                ->placeholder('Cognac / natural thread'),
            TextInput::make('description_az')
                ->label('Options (Azərbaycan)')
                ->placeholder('Konyak / təbii sap'),
```

and add the same mutate pair to the relation manager, with `TRANSLATABLE = ['description']`, using `mutateFormDataBeforeFill()` / `mutateFormDataBeforeSave()` on its edit action and `mutateFormDataBeforeCreate()` on its create action.

The table column stays `TextColumn::make('description')` — it renders the resolved string, which is what the operator wants to read.

- [ ] **Step 6: Run the tests**

Run: `php artisan test --filter="ProductTranslationTest|ProductResourceTest"`
Expected: PASS. `ProductResourceTest` from Plan 2A must stay green; if a test there fills `name`, update it to `name_en` — that is a legitimate consequence of this task, not a regression.

- [ ] **Step 7: Run the full suite and commit**

Run: `php artisan test`

```bash
git add app/Filament tests/Feature/Admin
git commit -m "feat: enter product content per locale in the admin"
```

---

## Task 3: Locale routing, switcher, and translation files

**Files:**
- Create: `app/Http/Middleware/SetLocale.php`
- Create: `lang/en/shop.php`, `lang/az/shop.php`
- Modify: `bootstrap/app.php`, `routes/web.php`
- Test: `tests/Feature/Storefront/LocaleRoutingTest.php`

**Interfaces:**
- Produces:
  - A `locale` route-prefix group. Every storefront route lives inside it and is named `storefront.*`.
  - `App\Http\Middleware\SetLocale`, aliased as `setlocale`.
  - `route('storefront.catalogue', ['locale' => app()->getLocale()])` style URL generation; a `locale` default is bound so `route('storefront.catalogue')` works inside a request.
  - Translation keys under the `shop` namespace, e.g. `__('shop.nav.cart')`.

**Placeholder routes:** Tasks 5-8 fill in the real controllers. This task registers the group with temporary closures so routing can be tested independently, and each later task replaces one closure with its controller.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Storefront/LocaleRoutingTest.php`:

```php
<?php // tests/Feature/Storefront/LocaleRoutingTest.php

it('redirects the bare root to the default locale', function () {
    $this->get('/')->assertRedirect('/en');
});

it('serves the English catalogue', function () {
    $this->get('/en')->assertSuccessful();
});

it('serves the Azerbaijani catalogue', function () {
    $this->get('/az')->assertSuccessful();
});

it('sets the application locale from the url', function () {
    $this->get('/az');

    expect(app()->getLocale())->toBe('az');
});

it('rejects an unsupported locale', function () {
    $this->get('/de')->assertNotFound();
});

it('keeps the payment callback outside the locale prefix', function () {
    // The gateway posts to a fixed URL. Prefixing it would break every callback.
    expect(route('payment.callback'))->not->toContain('/en/')
        ->and(route('payment.callback'))->not->toContain('/az/');
});

it('keeps the checkout post route working', function () {
    expect(route('checkout.store'))->toContain('/checkout');
});

it('translates a key differently per locale', function () {
    app()->setLocale('en');
    $en = __('shop.nav.cart');

    app()->setLocale('az');
    $az = __('shop.nav.cart');

    expect($en)->toBe('Cart')
        ->and($az)->toBe('Səbət')
        ->and($en)->not->toBe($az);
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=LocaleRoutingTest`
Expected: FAIL — `/` returns the stock welcome view rather than a redirect.

- [ ] **Step 3: Write the middleware**

Create `app/Http/Middleware/SetLocale.php`:

```php
<?php // app/Http/Middleware/SetLocale.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['en', 'az'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');

        abort_unless(in_array($locale, self::SUPPORTED, true), 404);

        app()->setLocale($locale);

        // So route('storefront.product', $slug) keeps the visitor's language
        // without every call site repeating it.
        URL::defaults(['locale' => $locale]);

        return $next($request);
    }
}
```

Add `use Illuminate\Support\Facades\URL;` to the imports.

- [ ] **Step 4: Register the alias**

In `bootstrap/app.php`, inside `->withMiddleware(function (Middleware $middleware) { ... })`, add:

```php
        $middleware->alias([
            'setlocale' => \App\Http\Middleware\SetLocale::class,
        ]);
```

Leave the existing `$middleware->validateCsrfTokens(except: ['payment/callback'])` call exactly as it is.

- [ ] **Step 5: Write the translation files**

Create `lang/en/shop.php`:

```php
<?php // lang/en/shop.php

return [
    'nav' => [
        'catalogue' => 'Shop',
        'cart' => 'Cart',
        'orders' => 'Find your order',
    ],
    'product' => [
        'add_to_cart' => 'Add to bag',
        'unavailable' => 'Currently unavailable',
        'made_to_order' => 'Made to order — ships in :days days',
        'personalization' => 'Personalisation',
    ],
    'cart' => [
        'title' => 'Your bag',
        'empty' => 'Your bag is empty.',
        'subtotal' => 'Subtotal',
        'remove' => 'Remove',
        'quantity' => 'Quantity',
        'checkout' => 'Checkout',
        'line_removed' => 'One item is no longer available and has been removed from your bag.',
    ],
    'checkout' => [
        'title' => 'Checkout',
        'shipping' => 'Shipping',
        'discount_code' => 'Discount code',
        'apply' => 'Apply',
        'place_order' => 'Place order',
        'total' => 'Total',
        'no_shipping' => 'We cannot ship to that country yet.',
    ],
    'catalogue' => [
        'title' => 'Shop',
        'empty' => 'Nothing here yet.',
    ],
];
```

Create `lang/az/shop.php`:

```php
<?php // lang/az/shop.php

return [
    'nav' => [
        'catalogue' => 'Mağaza',
        'cart' => 'Səbət',
        'orders' => 'Sifarişinizi tapın',
    ],
    'product' => [
        'add_to_cart' => 'Səbətə əlavə et',
        'unavailable' => 'Hazırda mövcud deyil',
        'made_to_order' => 'Sifarişlə hazırlanır — :days gün ərzində göndərilir',
        'personalization' => 'Fərdiləşdirmə',
    ],
    'cart' => [
        'title' => 'Səbətiniz',
        'empty' => 'Səbətiniz boşdur.',
        'subtotal' => 'Ara cəmi',
        'remove' => 'Sil',
        'quantity' => 'Say',
        'checkout' => 'Sifarişi tamamla',
        'line_removed' => 'Bir məhsul artıq mövcud deyil və səbətinizdən silindi.',
    ],
    'checkout' => [
        'title' => 'Sifarişi tamamla',
        'shipping' => 'Çatdırılma',
        'discount_code' => 'Endirim kodu',
        'apply' => 'Tətbiq et',
        'place_order' => 'Sifariş ver',
        'total' => 'Cəmi',
        'no_shipping' => 'Təəssüf ki, hələ o ölkəyə göndərmirik.',
    ],
    'catalogue' => [
        'title' => 'Mağaza',
        'empty' => 'Hələlik burada heç nə yoxdur.',
    ],
];
```

- [ ] **Step 6: Register the route group**

In `routes/web.php`, replace the existing `Route::get('/', fn () => view('welcome'));` with the redirect and the group. Leave `POST /checkout`, `POST /payment/callback`, the mock payment route and the admin login fallback exactly where they are — outside the prefix.

```php
Route::redirect('/', '/'.config('app.locale'));

Route::prefix('{locale}')
    ->middleware('setlocale')
    ->name('storefront.')
    ->group(function () {
        // Tasks 5-8 replace each closure with its controller.
        Route::get('/', fn () => response('catalogue'))->name('catalogue');
        Route::get('/product/{slug}', fn () => response('product'))->name('product');
        Route::get('/cart', fn () => response('cart'))->name('cart');
        Route::get('/checkout', fn () => response('checkout'))->name('checkout');
    });
```

- [ ] **Step 7: Run the tests**

Run: `php artisan test --filter=LocaleRoutingTest`
Expected: PASS, 8 tests.

- [ ] **Step 8: Run the full suite and commit**

Run: `php artisan test`
Expected: green. `welcome.blade.php` is now unreachable but stays on disk until Task 5 deletes it.

```bash
git add app/Http/Middleware bootstrap/app.php routes/web.php lang tests/Feature/Storefront
git commit -m "feat: add locale-prefixed storefront routing"
```

---

## Task 4: Design system and layout shell

The reference is Loro Piana: warm ivory ground, a single oxblood accent, serif type, and generous emptiness *inside* each frame.

**Files:**
- Modify: `package.json`, `resources/css/app.css`
- Create: `resources/views/components/layouts/storefront.blade.php`
- Create: `resources/views/components/site-header.blade.php`
- Create: `resources/views/components/site-footer.blade.php`
- Create: `resources/views/components/price.blade.php`
- Create: `resources/views/storefront/placeholder.blade.php` (deleted again in Task 5)
- Test: `tests/Feature/Storefront/LayoutTest.php`

**Interfaces:**
- Consumes: the `shop.*` translation keys and `storefront.*` route names from Task 3.
- Produces:
  - `<x-layouts.storefront :title="...">` — the shell every storefront page uses.
  - `<x-price :minor="4999" />` — renders an integer minor amount through `Money::format()`. **Every price in the storefront goes through this component**; no view formats money itself.
  - Tailwind theme tokens: `--color-ground`, `--color-tile`, `--color-ink`, `--color-muted`, `--color-accent`, `--font-serif`.

- [ ] **Step 1: Install the serif and verify it can set Azerbaijani**

```bash
npm install @fontsource/eb-garamond
ls node_modules/@fontsource/eb-garamond/
```

EB Garamond is the candidate: an old-style serif with the restraint the reference calls for. **It is only adopted if it ships the Azerbaijani set.** Confirm a `latin-ext` subset exists — that is the subset carrying the schwa:

```bash
ls node_modules/@fontsource/eb-garamond/ | grep latin-ext
grep -l "0259" node_modules/@fontsource/eb-garamond/*.css | head -3
```

Expected: `latin-ext-400.css` and siblings exist, and at least one CSS file declares a `unicode-range` covering U+0259.

If neither holds, stop and try `@fontsource/cormorant-garamond`, then `@fontsource/source-serif-4`, in that order. Report which was adopted. A face that cannot set the language is disqualified no matter how it looks.

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/Storefront/LayoutTest.php`:

```php
<?php // tests/Feature/Storefront/LayoutTest.php

use App\Domain\Catalog\Models\Product;

it('renders the shell with the brand name', function () {
    $this->get('/en')->assertSuccessful()->assertSee('Leather Shop');
});

it('offers a link to the other locale', function () {
    $this->get('/en')->assertSuccessful()->assertSee('/az', escape: false);
});

it('renders prices through the shared component', function () {
    Product::factory()->create(['base_price_minor' => 4999, 'is_active' => true]);

    $this->get('/en')->assertSee(App\Domain\Money::format(4999));
});

it('sets the html lang attribute from the locale', function () {
    $this->get('/az')->assertSee('lang="az"', escape: false);
});
```

- [ ] **Step 3: Run it to verify it fails**

Run: `php artisan test --filter=LayoutTest`
Expected: FAIL — the placeholder route from Task 3 returns the bare string `catalogue`.

- [ ] **Step 4: Add the theme tokens and font**

Replace the `@theme` block in `resources/css/app.css`, keeping the existing `@import 'tailwindcss';` and every `@source` line above it:

```css
@import '@fontsource/eb-garamond/latin-ext-400.css';
@import '@fontsource/eb-garamond/latin-ext-500.css';

@theme {
    /* Warm ivory, not white. The ground is what makes it read as premium. */
    --color-ground: #f6f3ee;
    /* A half-step deeper, so product photography on a neutral seamless
       background blends into one continuous field. */
    --color-tile: #edeae4;
    --color-ink: #1a2238;
    --color-muted: #6b6a66;
    /* One accent, used rarely: wordmark, active nav, price. */
    --color-accent: #8b3a2e;

    --font-serif: 'EB Garamond', ui-serif, Georgia, serif;
    --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
}
```

If Step 1 adopted a different face, change the `@import` lines and the `--font-serif` stack to match, and say so in the report.

Append the motion rule to the same file. Every transition in this plan is a
fade or a colour change of 300-500ms; nothing moves layout, and all of it stops
for anyone who has asked it to:

```css
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
}
```

Reveal-on-scroll and the hero crossfade belong to Plan 2C's home page; 2B's only
motion is the tile hover in Task 5. No content is ever gated behind JavaScript.

- [ ] **Step 5: Write the price component**

Create `resources/views/components/price.blade.php`:

```blade
@props(['minor'])

{{-- The one place the storefront turns minor units into text. --}}
<span {{ $attributes->merge(['class' => 'tabular-nums']) }}>{{ \App\Domain\Money::format($minor) }}</span>
```

- [ ] **Step 6: Write the header and footer**

Create `resources/views/components/site-header.blade.php`:

```blade
@php
    $otherLocale = app()->getLocale() === 'en' ? 'az' : 'en';
@endphp

<header class="border-b border-ink/10">
    <div class="bg-ground py-2 text-center font-serif text-xs tracking-widest text-muted">
        {{ __('shop.product.made_to_order', ['days' => 3]) }}
    </div>

    <div class="flex flex-col items-center gap-6 px-6 py-8">
        <a href="{{ route('storefront.catalogue') }}"
           class="font-serif text-2xl tracking-[0.2em] text-accent">
            Leather Shop
        </a>

        <nav class="flex items-center gap-10 font-serif text-sm tracking-widest">
            <a href="{{ route('storefront.catalogue') }}" class="hover:text-accent">{{ __('shop.nav.catalogue') }}</a>
            <a href="{{ route('orders.lookup') }}" class="hover:text-accent">{{ __('shop.nav.orders') }}</a>
            <a href="{{ route('storefront.cart') }}" class="hover:text-accent">
                {{ __('shop.nav.cart') }}
                {{-- Task 7 replaces this with <livewire:cart-count /> --}}
            </a>
            <a href="/{{ $otherLocale }}" class="uppercase text-muted hover:text-accent">{{ $otherLocale }}</a>
        </nav>
    </div>
</header>
```

The cart-count component does not exist until Task 7, so it is left as a comment here deliberately. Task 7 replaces that comment. Note this in the report so the reviewer expects it.

Create `resources/views/components/site-footer.blade.php`:

```blade
<footer class="mt-24 border-t border-ink/10 px-6 py-12 text-center font-serif text-xs tracking-widest text-muted">
    <p>Leather Shop — Baku</p>
    <p class="mt-2">
        <a href="{{ route('orders.lookup') }}" class="text-accent">{{ __('shop.nav.orders') }}</a>
    </p>
</footer>
```

- [ ] **Step 7: Write the layout**

Create `resources/views/components/layouts/storefront.blade.php`:

```blade
@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' — Leather Shop' : 'Leather Shop' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-ground font-sans text-ink antialiased">
    <x-site-header />

    <main>
        {{ $slot }}
    </main>

    <x-site-footer />

    @livewireScripts
</body>
</html>
```

- [ ] **Step 8: Point the catalogue placeholder at the layout**

So the layout is exercised before Task 5 exists, change the catalogue closure in `routes/web.php`:

```php
        Route::get('/', fn () => view('storefront.placeholder'))->name('catalogue');
```

Create `resources/views/storefront/placeholder.blade.php`:

```blade
<x-layouts.storefront>
    <div class="px-6 py-24 text-center">
        <h1 class="font-serif text-4xl tracking-wide">{{ __('shop.catalogue.title') }}</h1>
        @foreach (\App\Domain\Catalog\Models\Product::where('is_active', true)->get() as $product)
            <p class="mt-4"><x-price :minor="$product->base_price_minor" /></p>
        @endforeach
    </div>
</x-layouts.storefront>
```

Task 5 deletes this file and replaces the route with its controller.

- [ ] **Step 9: Build and verify the type renders Azerbaijani**

```bash
npm run build
```

Then check the schwa actually draws, rather than trusting subset metadata. Start the server, screenshot the Azerbaijani page, and **look at the image**:

```bash
php artisan serve --host=127.0.0.1 --port=8123 &
"/c/Program Files/Google/Chrome/Application/chrome.exe" --headless --disable-gpu \
  --screenshot=storefront-az.png --window-size=1280,900 http://127.0.0.1:8123/az
```

Expected: "Səbət" and "Sifarişinizi tapın" render with real glyphs. Boxes or obviously mismatched letterforms mean the face failed Step 1's promise — adopt the next candidate.

- [ ] **Step 10: Run the tests and commit**

Run: `php artisan test --filter=LayoutTest`
Expected: PASS, 4 tests.

Run: `php artisan test`

```bash
npm run build
git add package.json package-lock.json resources public/build routes/web.php tests/Feature/Storefront
git commit -m "feat: add storefront design system and layout shell"
```

`public/build` is committed deliberately — the production host has no Node.

---

## Task 5: Catalogue

**Files:**
- Create: `app/Http/Controllers/Storefront/CatalogueController.php`
- Create: `resources/views/storefront/catalogue.blade.php`
- Create: `resources/views/components/product-tile.blade.php`
- Delete: `resources/views/storefront/placeholder.blade.php`, `resources/views/welcome.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Storefront/CatalogueTest.php`

**Interfaces:**
- Consumes: `<x-layouts.storefront>` and `<x-price>` from Task 4.
- Produces: `<x-product-tile :product="$product" />`, reused by Plan 2C's home page.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Storefront/CatalogueTest.php`:

```php
<?php // tests/Feature/Storefront/CatalogueTest.php

use App\Domain\Catalog\Models\Product;

it('lists active products', function () {
    Product::factory()->create(['name' => 'Bifold wallet', 'is_active' => true]);

    $this->get('/en')->assertSuccessful()->assertSee('Bifold wallet');
});

it('hides inactive products', function () {
    Product::factory()->create(['name' => 'Secret prototype', 'is_active' => false]);

    $this->get('/en')->assertDontSee('Secret prototype');
});

it('shows the price in the grid', function () {
    Product::factory()->create(['base_price_minor' => 4999, 'is_active' => true]);

    $this->get('/en')->assertSee(App\Domain\Money::format(4999));
});

it('shows the product name in the active locale', function () {
    Product::factory()->create([
        'name' => ['en' => 'Bifold wallet', 'az' => 'İkiqat pulqabı'],
        'is_active' => true,
    ]);

    $this->get('/az')->assertSee('İkiqat pulqabı')->assertDontSee('Bifold wallet');
});

it('links each tile to its product page', function () {
    Product::factory()->create(['slug' => 'bifold-wallet', 'is_active' => true]);

    $this->get('/en')->assertSee('/en/product/bifold-wallet', escape: false);
});

it('shows a designed empty state rather than a blank page', function () {
    $this->get('/en')->assertSuccessful()->assertSee(__('shop.catalogue.empty'));
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=CatalogueTest`
Expected: FAIL — the placeholder view shows prices but no names or links.

- [ ] **Step 3: Write the controller**

Create `app/Http/Controllers/Storefront/CatalogueController.php`:

```php
<?php // app/Http/Controllers/Storefront/CatalogueController.php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class CatalogueController extends Controller
{
    public function __invoke(): View
    {
        $products = Product::query()
            ->where('is_active', true)
            ->with(['images' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('id')
            ->get();

        return view('storefront.catalogue', ['products' => $products]);
    }
}
```

- [ ] **Step 4: Write the tile**

Create `resources/views/components/product-tile.blade.php`:

```blade
@props(['product'])

@php
    $image = $product->images->first();
@endphp

<a href="{{ route('storefront.product', ['slug' => $product->slug]) }}" class="group block">
    {{-- Fixed aspect ratio with heavy internal padding: the product floats in
         its frame, and the emptiness inside the tile is the effect. --}}
    <div class="flex aspect-[3/4] items-center justify-center bg-tile p-12">
        @if ($image)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->path) }}"
                 alt="{{ $image->alt_text }}"
                 class="max-h-full max-w-full object-contain transition-opacity duration-500 group-hover:opacity-90">
        @else
            {{-- Placeholder holds the ratio so the grid never collapses before
                 real photography arrives. --}}
            <span class="font-serif text-sm tracking-widest text-muted">{{ $product->name }}</span>
        @endif
    </div>

    <div class="py-4 text-center">
        <h2 class="font-serif text-base">{{ $product->name }}</h2>
        <p class="mt-1 font-serif text-sm text-accent"><x-price :minor="$product->base_price_minor" /></p>
    </div>
</a>
```

- [ ] **Step 5: Write the catalogue view**

Create `resources/views/storefront/catalogue.blade.php`:

```blade
<x-layouts.storefront :title="__('shop.catalogue.title')">
    @if ($products->isEmpty())
        <div class="px-6 py-32 text-center font-serif text-lg tracking-wide text-muted">
            {{ __('shop.catalogue.empty') }}
        </div>
    @else
        {{-- Edge to edge, hairline gutters. The white space lives inside the tiles. --}}
        <div class="grid grid-cols-1 gap-px sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($products as $product)
                <x-product-tile :product="$product" />
            @endforeach
        </div>
    @endif
</x-layouts.storefront>
```

- [ ] **Step 6: Point the route at the controller and delete the placeholders**

In `routes/web.php`, inside the locale group, with `use App\Http\Controllers\Storefront\CatalogueController;` imported:

```php
        Route::get('/', CatalogueController::class)->name('catalogue');
```

Then remove both placeholder views:

```bash
rm resources/views/storefront/placeholder.blade.php
git rm resources/views/welcome.blade.php
```

- [ ] **Step 7: Run the tests and commit**

Run: `php artisan test --filter="CatalogueTest|LayoutTest"`
Expected: PASS, 10 tests.

Run: `php artisan test`

```bash
git add app/Http/Controllers/Storefront resources/views routes/web.php tests/Feature/Storefront
git commit -m "feat: add storefront catalogue"
```

---

## Task 6: Product detail and the purchase island

**Files:**
- Create: `app/Http/Controllers/Storefront/ProductController.php`
- Create: `app/Livewire/ProductPurchase.php`
- Create: `resources/views/storefront/product.blade.php`
- Create: `resources/views/livewire/product-purchase.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Storefront/ProductPageTest.php`, `tests/Feature/Livewire/ProductPurchaseTest.php`

**Interfaces:**
- Consumes: `CartService::add(int $variantId, int $quantity, array $personalization)`; `Variant::effectivePriceMinor()`; the layout and price components.
- Produces: a `cart-updated` Livewire event dispatched after a successful add. Task 7's `CartCount` listens for it.

**Do not apply the personalization delta twice.** `CartService::snapshot()` already folds `price_delta_minor` into `CartLine::$unitPriceMinor`. The live price shown here must equal `effectivePriceMinor() + sum(selected deltas)` so the cart never contradicts the product page.

- [ ] **Step 1: Find the personalization validator's exception type**

```bash
grep -rn "class .*Exception" app/Domain/Cart/
grep -rn "validate" app/Domain/Cart/CartService.php
```

`CartService::add()` calls `$this->validator->validate($variant->product, $personalization)`. Note the exception that method throws — Step 3 catches it by name. Report which class it is.

- [ ] **Step 2: Write the failing component test**

Create `tests/Feature/Livewire/ProductPurchaseTest.php`:

```php
<?php // tests/Feature/Livewire/ProductPurchaseTest.php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\Models\PersonalizationOption;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Livewire\ProductPurchase;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->product = Product::factory()->create(['base_price_minor' => 8900, 'is_active' => true]);
    $this->variant = Variant::factory()->for($this->product)->create([
        'stock_quantity' => 5,
        'price_minor_override' => null,
        'is_active' => true,
    ]);
});

it('starts on the first available variant', function () {
    livewire(ProductPurchase::class, ['product' => $this->product])
        ->assertSet('variantId', $this->variant->id);
});

it('shows the effective price', function () {
    livewire(ProductPurchase::class, ['product' => $this->product])
        ->assertSee(App\Domain\Money::format(8900));
});

it('prefers the variant price override', function () {
    $this->variant->update(['price_minor_override' => 9500]);

    livewire(ProductPurchase::class, ['product' => $this->product->fresh()])
        ->assertSee(App\Domain\Money::format(9500));
});

it('adds the variant to the cart', function () {
    livewire(ProductPurchase::class, ['product' => $this->product])
        ->call('add');

    expect(app(CartService::class)->snapshot()->lines)->toHaveCount(1);
});

it('announces the change so the header can update', function () {
    livewire(ProductPurchase::class, ['product' => $this->product])
        ->call('add')
        ->assertDispatched('cart-updated');
});

it('adds the personalization delta to the displayed price', function () {
    PersonalizationOption::create([
        'product_id' => $this->product->id,
        'type' => 'monogram',
        'label' => 'Monogram',
        'price_delta_minor' => 500,
        'max_characters' => 3,
        'allowed_pattern' => '/^[A-Z]+$/',
        'is_required' => false,
    ]);

    livewire(ProductPurchase::class, ['product' => $this->product->fresh()])
        ->set('personalization.monogram', 'MA')
        ->assertSee(App\Domain\Money::format(9400));
});

it('surfaces a personalization rule violation instead of adding the line', function () {
    PersonalizationOption::create([
        'product_id' => $this->product->id,
        'type' => 'monogram',
        'label' => 'Monogram',
        'price_delta_minor' => 0,
        'max_characters' => 3,
        'allowed_pattern' => '/^[A-Z]+$/',
        'is_required' => false,
    ]);

    livewire(ProductPurchase::class, ['product' => $this->product->fresh()])
        ->set('personalization.monogram', 'lowercase!')
        ->call('add')
        ->assertHasErrors('personalization.monogram');

    expect(app(CartService::class)->snapshot()->lines)->toBeEmpty();
});

it('refuses to add a variant with no remaining capacity', function () {
    $this->variant->update(['stock_quantity' => 0]);

    livewire(ProductPurchase::class, ['product' => $this->product->fresh()])
        ->call('add');

    expect(app(CartService::class)->snapshot()->lines)->toBeEmpty();
});
```

- [ ] **Step 3: Run it to verify it fails**

Run: `php artisan test --filter=ProductPurchaseTest`
Expected: FAIL — `Class "App\Livewire\ProductPurchase" not found`.

- [ ] **Step 4: Write the component**

Create `app/Livewire/ProductPurchase.php`. Replace `ValidationException` with whatever Step 1 found:

```php
<?php // app/Livewire/ProductPurchase.php

namespace App\Livewire;

use App\Domain\Cart\CartService;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ProductPurchase extends Component
{
    public Product $product;

    public ?int $variantId = null;

    /** type => value, e.g. ['monogram' => 'MA'] */
    public array $personalization = [];

    public function mount(Product $product): void
    {
        $this->product = $product;

        $this->variantId = $product->variants
                ->first(fn (Variant $v) => $v->is_active && $v->stock_quantity > 0)?->id
            ?? $product->variants->firstWhere('is_active', true)?->id;
    }

    public function getVariantProperty(): ?Variant
    {
        return $this->product->variants->firstWhere('id', $this->variantId);
    }

    /** Capacity is an operator-set cap; at zero the piece cannot be committed to. */
    public function getAvailableProperty(): bool
    {
        return $this->variant !== null && $this->variant->stock_quantity > 0;
    }

    /**
     * Mirrors CartService::snapshot() exactly: effective price plus the delta of
     * every selected personalization option. The cart must never disagree with
     * what the customer was shown here.
     */
    public function getUnitPriceMinorProperty(): int
    {
        if (! $this->variant) {
            return 0;
        }

        $delta = $this->product->personalizationOptions
            ->whereIn('type', array_keys(array_filter($this->personalization)))
            ->sum('price_delta_minor');

        return $this->variant->effectivePriceMinor() + $delta;
    }

    public function add(CartService $cart): void
    {
        if (! $this->available) {
            return;
        }

        try {
            // CartService validates personalization against the product's own
            // options. The storefront does not restate those rules.
            $cart->add($this->variantId, 1, array_filter($this->personalization));
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError('personalization.'.$field, $messages[0]);
            }

            return;
        }

        $this->dispatch('cart-updated');
    }

    public function render()
    {
        return view('livewire.product-purchase');
    }
}
```

If the validator throws a domain exception carrying a single message rather than a field map, catch that class and call `$this->addError('personalization.'.$option->type, $e->getMessage())` against the offending option instead. Report which shape was used.

- [ ] **Step 5: Write the component view**

Create `resources/views/livewire/product-purchase.blade.php`:

```blade
<div>
    <p class="font-serif text-xl text-accent">
        <x-price :minor="$this->unitPriceMinor" />
    </p>

    @if ($product->variants->where('is_active', true)->count() > 1)
        <div class="mt-6 flex flex-wrap gap-2">
            @foreach ($product->variants->where('is_active', true) as $variant)
                <button type="button"
                        wire:click="$set('variantId', {{ $variant->id }})"
                        @class([
                            'border px-4 py-2 font-serif text-sm tracking-wide',
                            'border-accent text-accent' => $variantId === $variant->id,
                            'border-ink/20 hover:border-ink/40' => $variantId !== $variant->id,
                        ])>
                    {{ $variant->description }}
                </button>
            @endforeach
        </div>
    @endif

    @foreach ($product->personalizationOptions as $option)
        <div class="mt-6">
            <label class="block font-sans text-xs uppercase tracking-widest text-muted">
                {{ $option->label }}
            </label>
            <input type="text"
                   wire:model.live="personalization.{{ $option->type }}"
                   maxlength="{{ $option->max_characters }}"
                   class="mt-2 w-full border border-ink/20 bg-transparent px-3 py-2 font-sans">
            @error('personalization.'.$option->type)
                <p class="mt-1 font-sans text-xs text-accent">{{ $message }}</p>
            @enderror
        </div>
    @endforeach

    <p class="mt-6 font-sans text-xs tracking-wide text-muted">
        {{ __('shop.product.made_to_order', ['days' => $product->lead_time_days]) }}
    </p>

    @if ($this->available)
        <button type="button" wire:click="add"
                class="mt-6 w-full bg-ink px-6 py-4 font-serif text-sm tracking-widest text-ground hover:bg-accent">
            {{ __('shop.product.add_to_cart') }}
        </button>
    @else
        <p class="mt-6 border border-ink/20 px-6 py-4 text-center font-serif text-sm tracking-widest text-muted">
            {{ __('shop.product.unavailable') }}
        </p>
    @endif
</div>
```

- [ ] **Step 6: Write the page test**

Create `tests/Feature/Storefront/ProductPageTest.php`:

```php
<?php // tests/Feature/Storefront/ProductPageTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;

it('shows an active product by slug', function () {
    $product = Product::factory()->create([
        'slug' => 'bifold-wallet', 'name' => 'Bifold wallet', 'is_active' => true,
    ]);
    Variant::factory()->for($product)->create(['is_active' => true, 'stock_quantity' => 3]);

    $this->get('/en/product/bifold-wallet')->assertSuccessful()->assertSee('Bifold wallet');
});

it('404s on an inactive product', function () {
    Product::factory()->create(['slug' => 'hidden', 'is_active' => false]);

    $this->get('/en/product/hidden')->assertNotFound();
});

it('404s on an unknown slug', function () {
    $this->get('/en/product/nope')->assertNotFound();
});

it('shows the story in the active locale', function () {
    $product = Product::factory()->create([
        'slug' => 'bifold-wallet',
        'is_active' => true,
        'story' => ['en' => 'Cut by hand.', 'az' => 'Əl ilə kəsilir.'],
    ]);
    Variant::factory()->for($product)->create(['is_active' => true, 'stock_quantity' => 2]);

    $this->get('/az/product/bifold-wallet')->assertSee('Əl ilə kəsilir.');
});
```

- [ ] **Step 7: Write the controller and page**

Create `app/Http/Controllers/Storefront/ProductController.php`:

```php
<?php // app/Http/Controllers/Storefront/ProductController.php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ProductController extends Controller
{
    /** $locale comes first: it is the route group's prefix parameter. */
    public function __invoke(string $locale, string $slug): View
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with(['variants', 'images' => fn ($q) => $q->orderBy('sort_order'), 'personalizationOptions'])
            ->firstOrFail();

        return view('storefront.product', ['product' => $product]);
    }
}
```

Create `resources/views/storefront/product.blade.php`:

```blade
<x-layouts.storefront :title="$product->name">
    <div class="grid grid-cols-1 lg:grid-cols-2">
        <div class="flex aspect-[3/4] items-center justify-center bg-tile p-16">
            @if ($image = $product->images->first())
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->path) }}"
                     alt="{{ $image->alt_text }}"
                     class="max-h-full max-w-full object-contain">
            @else
                <span class="font-serif text-sm tracking-widest text-muted">{{ $product->name }}</span>
            @endif
        </div>

        <div class="px-8 py-16 lg:px-16">
            <h1 class="font-serif text-3xl tracking-wide">{{ $product->name }}</h1>

            @if ($product->description)
                <p class="mt-6 font-sans text-sm leading-relaxed text-muted">{{ $product->description }}</p>
            @endif

            <livewire:product-purchase :product="$product" />

            @if ($product->story)
                <div class="mt-16 border-t border-ink/10 pt-8">
                    <p class="font-serif text-sm leading-relaxed">{{ $product->story }}</p>
                </div>
            @endif
        </div>
    </div>
</x-layouts.storefront>
```

- [ ] **Step 8: Register the route**

Replace the product closure in `routes/web.php`, importing `App\Http\Controllers\Storefront\ProductController`:

```php
        Route::get('/product/{slug}', ProductController::class)->name('product');
```

- [ ] **Step 9: Run the tests and commit**

Run: `php artisan test --filter="ProductPurchaseTest|ProductPageTest"`
Expected: PASS, 12 tests.

Run: `php artisan test`

```bash
git add app/Livewire app/Http/Controllers/Storefront resources/views routes/web.php tests
git commit -m "feat: add product page with variant and personalization selection"
```

---

## Task 7: Cart

**Files:**
- Create: `app/Livewire/CartPage.php`, `app/Livewire/CartCount.php`
- Create: `resources/views/livewire/cart-page.blade.php`, `resources/views/livewire/cart-count.blade.php`
- Modify: `resources/views/components/site-header.blade.php`, `routes/web.php`
- Test: `tests/Feature/Livewire/CartPageTest.php`

**Interfaces:**
- Consumes: `CartService::snapshot()`, `remove(string $lineKey)`, `add()`; the `cart-updated` event from Task 6.
- Produces: `<livewire:cart-count />`, mounted in the header.

**The dropped-line problem.** `CartService::snapshot()` silently skips any line whose variant or product went inactive — a bare `continue`. A customer would watch an item vanish with no explanation. `CartPage` therefore compares the raw session line count against the snapshot's and shows `shop.cart.line_removed` when they differ. This is presentation only: the domain's behaviour is correct and stays untouched.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Livewire/CartPageTest.php`:

```php
<?php // tests/Feature/Livewire/CartPageTest.php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Livewire\CartCount;
use App\Livewire\CartPage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->product = Product::factory()->create(['base_price_minor' => 8900, 'is_active' => true]);
    $this->variant = Variant::factory()->for($this->product)->create([
        'stock_quantity' => 5, 'is_active' => true, 'weight_grams' => 120,
    ]);
});

it('shows an empty state when nothing has been added', function () {
    livewire(CartPage::class)->assertSee(__('shop.cart.empty'));
});

it('lists a line that was added', function () {
    app(CartService::class)->add($this->variant->id, 1);

    livewire(CartPage::class)->assertSee($this->product->name);
});

it('shows the subtotal', function () {
    app(CartService::class)->add($this->variant->id, 2);

    livewire(CartPage::class)->assertSee(App\Domain\Money::format(17800));
});

it('removes a line', function () {
    app(CartService::class)->add($this->variant->id, 1);
    $lineKey = app(CartService::class)->snapshot()->lines[0]->lineKey;

    livewire(CartPage::class)->call('remove', $lineKey);

    expect(app(CartService::class)->snapshot()->lines)->toBeEmpty();
});

it('tells the customer when a line disappeared because the product was retired', function () {
    app(CartService::class)->add($this->variant->id, 1);

    // The operator deactivates it while the cart is still open.
    $this->product->update(['is_active' => false]);

    livewire(CartPage::class)->assertSee(__('shop.cart.line_removed'));
});

it('counts the lines in the header', function () {
    app(CartService::class)->add($this->variant->id, 3);

    livewire(CartCount::class)->assertSee('3');
});

it('refreshes the header count when a product page adds something', function () {
    livewire(CartCount::class)
        ->assertSee('0')
        ->call('$refresh');

    app(CartService::class)->add($this->variant->id, 1);

    livewire(CartCount::class)->assertSee('1');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=CartPageTest`
Expected: FAIL — `Class "App\Livewire\CartPage" not found`.

- [ ] **Step 3: Write the cart count component**

Create `app/Livewire/CartCount.php`:

```php
<?php // app/Livewire/CartCount.php

namespace App\Livewire;

use App\Domain\Cart\CartService;
use Livewire\Attributes\On;
use Livewire\Component;

class CartCount extends Component
{
    public function render(CartService $cart)
    {
        return view('livewire.cart-count', [
            'count' => array_sum(array_map(
                fn ($line) => $line->quantity,
                $cart->snapshot()->lines
            )),
        ]);
    }

    /** Dispatched by ProductPurchase after a successful add. */
    #[On('cart-updated')]
    public function refreshCount(): void
    {
        // Re-rendering is the whole job; the render method reads the session.
    }
}
```

Create `resources/views/livewire/cart-count.blade.php`:

```blade
<span>({{ $count }})</span>
```

- [ ] **Step 4: Write the cart page component**

Create `app/Livewire/CartPage.php`:

```php
<?php // app/Livewire/CartPage.php

namespace App\Livewire;

use App\Domain\Cart\CartService;
use Livewire\Component;

class CartPage extends Component
{
    public function remove(CartService $cart, string $lineKey): void
    {
        $cart->remove($lineKey);

        $this->dispatch('cart-updated');
    }

    public function render(CartService $cart)
    {
        $snapshot = $cart->snapshot();

        // snapshot() silently drops lines whose variant or product was
        // deactivated. Watching an item vanish with no explanation is worse
        // than the retirement itself, so say so.
        $rawLineCount = count(session('cart', []));
        $dropped = $rawLineCount > count($snapshot->lines);

        return view('livewire.cart-page', [
            'lines' => $snapshot->lines,
            'subtotalMinor' => $snapshot->subtotalMinor(),
            'dropped' => $dropped,
        ]);
    }
}
```

**Verify the session key** before writing this: `CartService` stores lines under a `self::KEY` constant. Read `app/Domain/Cart/CartService.php` and use the real key rather than the literal `'cart'` above. Report the value used.

Create `resources/views/livewire/cart-page.blade.php`:

```blade
<div class="mx-auto max-w-3xl px-6 py-16">
    <h1 class="font-serif text-3xl tracking-wide">{{ __('shop.cart.title') }}</h1>

    @if ($dropped)
        <p class="mt-6 border border-accent/40 px-4 py-3 font-sans text-sm text-accent">
            {{ __('shop.cart.line_removed') }}
        </p>
    @endif

    @if (empty($lines))
        <p class="mt-16 text-center font-serif text-lg text-muted">{{ __('shop.cart.empty') }}</p>
    @else
        <ul class="mt-12 divide-y divide-ink/10">
            @foreach ($lines as $line)
                <li class="flex items-start justify-between py-6">
                    <div>
                        <p class="font-serif text-base">{{ $line->productName }}</p>
                        <p class="mt-1 font-sans text-xs text-muted">{{ $line->variantDescription }}</p>

                        @foreach ($line->personalization as $key => $value)
                            <p class="mt-1 font-sans text-xs text-muted">
                                {{ \Illuminate\Support\Str::of($key)->headline() }}: {{ $value }}
                            </p>
                        @endforeach

                        <p class="mt-2 font-sans text-xs text-muted">
                            {{ __('shop.cart.quantity') }}: {{ $line->quantity }}
                        </p>
                    </div>

                    <div class="text-right">
                        <p class="font-serif text-base"><x-price :minor="$line->lineTotalMinor()" /></p>
                        <button type="button"
                                wire:click="remove('{{ $line->lineKey }}')"
                                class="mt-2 font-sans text-xs uppercase tracking-widest text-muted hover:text-accent">
                            {{ __('shop.cart.remove') }}
                        </button>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-8 flex items-center justify-between border-t border-ink/10 pt-6">
            <span class="font-serif text-lg">{{ __('shop.cart.subtotal') }}</span>
            <span class="font-serif text-lg"><x-price :minor="$subtotalMinor" /></span>
        </div>

        <a href="{{ route('storefront.checkout') }}"
           class="mt-8 block bg-ink px-6 py-4 text-center font-serif text-sm tracking-widest text-ground hover:bg-accent">
            {{ __('shop.cart.checkout') }}
        </a>
    @endif
</div>
```

- [ ] **Step 5: Mount the count in the header and register the route**

In `resources/views/components/site-header.blade.php`, replace the placeholder comment from Task 4 with the real component:

```blade
            <a href="{{ route('storefront.cart') }}" class="hover:text-accent">
                {{ __('shop.nav.cart') }} <livewire:cart-count />
            </a>
```

In `routes/web.php`, replace the cart closure:

```php
        Route::get('/cart', CartPage::class)->name('cart');
```

with `use App\Livewire\CartPage;` imported. A Livewire component can be routed directly; it renders inside the storefront layout via `#[Layout]`. Add to `CartPage`:

```php
use Livewire\Attributes\Layout;

#[Layout('components.layouts.storefront')]
class CartPage extends Component
```

- [ ] **Step 6: Run the tests and commit**

Run: `php artisan test --filter=CartPageTest`
Expected: PASS, 7 tests.

Run: `php artisan test`

```bash
git add app/Livewire resources/views routes/web.php tests/Feature/Livewire
git commit -m "feat: add storefront cart"
```

---

## Task 8: PlaceOrder extraction and checkout

**Files:**
- Create: `app/Domain/Checkout/PlaceOrder.php`
- Create: `app/Livewire/CheckoutForm.php`, `resources/views/livewire/checkout-form.blade.php`
- Modify: `app/Http/Controllers/CheckoutController.php`, `routes/web.php`
- Test: `tests/Feature/Livewire/CheckoutFormTest.php`

**Interfaces:**
- Consumes: `CartService::snapshot()`, `ShippingCalculator::quotesFor()/quoteById()`, `DiscountService::apply()`, `OrderService::createFromCart()`, `PaymentGateway`.
- Produces: `App\Domain\Checkout\PlaceOrder::__invoke(CustomerDetails $customer, int $shippingRateId, ?string $discountCode, string $locale): PlaceOrderResult` — the single write path. Both `CheckoutController` and `CheckoutForm` call it.

**This is the only refactor of tested code in the plan.** Plan 1's existing checkout tests are the regression guard: they must pass **untouched**. If they need editing to stay green, the extraction changed behaviour and is wrong.

- [ ] **Step 1: Read the controller before touching it**

```bash
cat app/Http/Controllers/CheckoutController.php
ls tests/Feature/Checkout/
```

Note every branch: empty cart, `NoShippingRateException`, `InvalidDiscountException`, `InsufficientStockException`, and the gateway-unreachable path Plan 1 added. **Every one of them moves into `PlaceOrder` unchanged.** List them in the report before writing code.

- [ ] **Step 2: Extract PlaceOrder with no behaviour change**

Create `app/Domain/Checkout/PlaceOrder.php`. It receives already-validated input and returns either a redirect target or a typed failure. Model it directly on the controller's existing body — same order of operations, same exception handling, same logging:

```php
<?php // app/Domain/Checkout/PlaceOrder.php

namespace App\Domain\Checkout;

use App\Domain\Cart\CartService;
use App\Domain\Discount\DiscountService;
use App\Domain\Order\CustomerDetails;
use App\Domain\Order\OrderService;
use App\Domain\Payment\PaymentGateway;
use App\Domain\Shipping\ShippingCalculator;

/**
 * The single write path for placing an order.
 *
 * Two entry points need this behaviour — the POST /checkout route kept from
 * Plan 1, and the Livewire checkout form — and neither may own a second copy
 * of it. Same reasoning as TransitionActions funnelling every admin status
 * change through OrderService::transition().
 */
class PlaceOrder
{
    public function __construct(
        private CartService $cart,
        private ShippingCalculator $shipping,
        private DiscountService $discounts,
        private OrderService $orders,
        private PaymentGateway $gateway,
    ) {}

    public function __invoke(
        CustomerDetails $customer,
        int $shippingRateId,
        ?string $discountCode,
        string $locale,
    ): PlaceOrderResult {
        // Body moved verbatim from CheckoutController::store(), with the
        // redirect/back() calls replaced by PlaceOrderResult values and
        // $locale recorded on the order.
    }
}
```

Create `app/Domain/Checkout/PlaceOrderResult.php` as a small value object carrying `bool $succeeded`, `?string $redirectUrl`, `?string $errorField`, `?string $errorMessage`. The controller maps a failure back onto `back()->withErrors([...])`; the Livewire form maps it onto `addError()`.

- [ ] **Step 3: Make the controller call it**

Rewrite `CheckoutController::store()` so it validates via the existing `CheckoutRequest`, builds `CustomerDetails`, calls `PlaceOrder`, and translates the result into the same redirects and error bags it produced before. The route, the request class and the response shapes do not change.

- [ ] **Step 4: Prove the extraction changed nothing**

Run: `php artisan test --filter=Checkout`
Expected: every Plan 1 checkout test passes **without being edited**. If one needs editing, revert and redo the extraction — the guard has caught a real behaviour change.

- [ ] **Step 5: Write the failing checkout form test**

Create `tests/Feature/Livewire/CheckoutFormTest.php`:

```php
<?php // tests/Feature/Livewire/CheckoutFormTest.php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Models\ShippingRate;
use App\Domain\Shipping\Models\ShippingZone;
use App\Livewire\CheckoutForm;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $product = Product::factory()->create(['base_price_minor' => 8900, 'is_active' => true]);
    $this->variant = Variant::factory()->for($product)->create([
        'stock_quantity' => 5, 'is_active' => true, 'weight_grams' => 120,
    ]);

    $zone = ShippingZone::create([
        'name' => 'Azerbaijan', 'country_codes' => ['AZ'], 'is_fallback' => true,
    ]);
    $this->rate = ShippingRate::create([
        'shipping_zone_id' => $zone->id, 'name' => 'Standard',
        'min_weight_grams' => 0, 'max_weight_grams' => 3000, 'price_minor' => 500,
    ]);

    app(CartService::class)->add($this->variant->id, 1);
});

it('offers shipping options once a country is chosen', function () {
    livewire(CheckoutForm::class)
        ->set('country_code', 'AZ')
        ->assertSee('Standard');
});

it('reports honestly when nothing ships to that country', function () {
    livewire(CheckoutForm::class)
        ->set('country_code', 'ZZ')
        ->assertSee(__('shop.checkout.no_shipping'));
});

it('places an order through the shared PlaceOrder path', function () {
    livewire(CheckoutForm::class)
        ->set('country_code', 'AZ')
        ->set('shipping_rate_id', $this->rate->id)
        ->set('email', 'buyer@example.com')
        ->set('name', 'Buyer')
        ->set('address_line1', '1 Nizami St')
        ->set('city', 'Baku')
        ->call('submit');

    expect(Order::count())->toBe(1);
});

it('records the locale the customer bought in', function () {
    app()->setLocale('az');

    livewire(CheckoutForm::class)
        ->set('country_code', 'AZ')
        ->set('shipping_rate_id', $this->rate->id)
        ->set('email', 'buyer@example.com')
        ->set('name', 'Buyer')
        ->set('address_line1', '1 Nizami St')
        ->set('city', 'Baku')
        ->call('submit');

    expect(Order::sole()->locale)->toBe('az');
});

it('refuses to submit without an email', function () {
    livewire(CheckoutForm::class)
        ->set('country_code', 'AZ')
        ->set('shipping_rate_id', $this->rate->id)
        ->set('name', 'Buyer')
        ->set('address_line1', '1 Nizami St')
        ->set('city', 'Baku')
        ->call('submit')
        ->assertHasErrors('email');

    expect(Order::count())->toBe(0);
});
```

- [ ] **Step 6: Write the component**

Create `app/Livewire/CheckoutForm.php`:

```php
<?php // app/Livewire/CheckoutForm.php

namespace App\Livewire;

use App\Domain\Cart\CartService;
use App\Domain\Checkout\PlaceOrder;
use App\Domain\Order\CustomerDetails;
use App\Domain\Shipping\ShippingCalculator;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.storefront')]
class CheckoutForm extends Component
{
    public string $email = '';
    public string $name = '';
    public string $phone = '';
    public string $address_line1 = '';
    public string $address_line2 = '';
    public string $city = '';
    public string $postcode = '';
    public string $country_code = 'AZ';
    public ?int $shipping_rate_id = null;
    public string $discount_code = '';

    /** ShippingQuote[] for the current country and cart weight. */
    public array $quotes = [];

    public function mount(): void
    {
        $this->refreshQuotes();
    }

    /** Livewire calls this automatically whenever country_code changes. */
    public function updatedCountryCode(): void
    {
        $this->shipping_rate_id = null;
        $this->refreshQuotes();
    }

    /**
     * Shipping is priced by the domain, never in the view: the calculator owns
     * the zone and weight-bracket rules.
     */
    private function refreshQuotes(): void
    {
        $weight = app(CartService::class)->snapshot()->totalWeightGrams();

        $this->quotes = app(ShippingCalculator::class)
            ->quotesFor($this->country_code, $weight);

        if (count($this->quotes) === 1) {
            $this->shipping_rate_id = $this->quotes[0]->rateId;
        }
    }

    public function submit(PlaceOrder $placeOrder)
    {
        // Mirror CheckoutRequest's rules. Read that class and reconcile any
        // difference in its favour — it guards the POST route that Plan 1's
        // tests cover.
        $this->validate([
            'email' => ['required', 'email'],
            'name' => ['required', 'string', 'max:255'],
            'address_line1' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2'],
            'shipping_rate_id' => ['required', 'integer'],
        ]);

        $result = $placeOrder(
            new CustomerDetails(
                email: $this->email,
                name: $this->name,
                addressLine1: $this->address_line1,
                addressLine2: $this->address_line2 ?: null,
                city: $this->city,
                postcode: $this->postcode ?: null,
                countryCode: $this->country_code,
                phone: $this->phone ?: null,
            ),
            $this->shipping_rate_id,
            $this->discount_code ?: null,
            app()->getLocale(),
        );

        if (! $result->succeeded) {
            $this->addError($result->errorField ?? 'email', $result->errorMessage);

            return null;
        }

        return redirect($result->redirectUrl);
    }

    public function render()
    {
        return view('livewire.checkout-form', [
            'snapshot' => app(CartService::class)->snapshot(),
        ]);
    }
}
```

Confirm `CartSnapshot::totalWeightGrams()` is the real method name before
writing `refreshQuotes()` — `OrderService::createFromCart()` calls it, so it
exists, but check the spelling.

If `$this->quotes` is empty after a country change, the view shows
`shop.checkout.no_shipping` rather than an empty radio list.

Create `resources/views/livewire/checkout-form.blade.php` following the cart page's visual language: `max-w-3xl`, serif headings, `border-ink/20` inputs, the ink button for the primary action. Show the shipping options as radio buttons rendered from the re-quoted list, and the order total via `<x-price>`.

- [ ] **Step 7: Register the route**

```php
        Route::get('/checkout', CheckoutForm::class)->name('checkout');
```

- [ ] **Step 8: Run the tests and commit**

Run: `php artisan test --filter="CheckoutFormTest|Checkout"`
Expected: PASS, including Plan 1's untouched checkout tests.

Run: `php artisan test`

```bash
git add app/Domain/Checkout app/Livewire app/Http/Controllers/CheckoutController.php resources/views routes/web.php tests
git commit -m "feat: add storefront checkout over a shared PlaceOrder path"
```

---

## Task 9: Confirmation, order lookup, and order locale

**Files:**
- Modify: `resources/views/checkout/confirmation.blade.php`
- Modify: `resources/views/orders/lookup.blade.php`, `resources/views/orders/show.blade.php`
- Modify: `app/Mail/OrderConfirmation.php`, `app/Mail/ShipmentNotification.php`
- Test: `tests/Feature/Storefront/OrderLocaleTest.php`

**Interfaces:**
- Consumes: `orders.locale` from Task 1; the layout from Task 4.
- Produces: nothing later tasks depend on. This is the last task.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Storefront/OrderLocaleTest.php`:

```php
<?php // tests/Feature/Storefront/OrderLocaleTest.php

use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Order\OrderStatus;
use App\Mail\ShipmentNotification;
use Illuminate\Support\Facades\Mail;

it('renders the lookup page inside the storefront layout', function () {
    $this->get('/orders/lookup')->assertSuccessful()->assertSee('Leather Shop');
});

it('finds an order and shows its snapshot lines', function () {
    $order = Order::factory()->create(['customer_email' => 'buyer@example.com']);
    OrderItem::factory()->for($order)->create(['product_name' => 'Bifold wallet']);

    $this->post('/orders/lookup', [
        'order_number' => $order->order_number,
        'email' => 'buyer@example.com',
    ])->assertSuccessful()->assertSee('Bifold wallet');
});

it('sends the shipment email in the language the customer ordered in', function () {
    Mail::fake();

    $order = Order::factory()->create([
        'status' => OrderStatus::InProduction,
        'paid_at' => now()->subDay(),
        'locale' => 'az',
    ]);
    OrderItem::factory()->for($order)->create();

    app(App\Domain\Order\OrderService::class)
        ->transition($order, OrderStatus::Shipped, trackingNumber: 'AZ123456789AZ');

    Mail::assertQueued(ShipmentNotification::class, function ($mail) {
        return $mail->order->locale === 'az';
    });
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=OrderLocaleTest`
Expected: FAIL — the lookup view is bare HTML with no layout.

- [ ] **Step 3: Restyle the three existing views**

Wrap each in `<x-layouts.storefront>` and apply the established vocabulary — serif headings, `border-ink/20` inputs, `<x-price>` for every amount. Keep every existing form field name, action and route exactly as they are: `OrderLookupController` and `CheckoutConfirmationController` are tested and must not change.

- [ ] **Step 4: Localise the mailables**

In `ShipmentNotification` and `OrderConfirmation`, set the locale from the order before rendering, so the email matches what the customer saw:

```php
    public function envelope(): Envelope
    {
        app()->setLocale($this->order->locale ?? config('app.fallback_locale'));

        return new Envelope(subject: __('shop.mail.shipped', ['number' => $this->order->order_number]));
    }
```

Add `mail.shipped` and any other mail strings to both `lang/en/shop.php` and `lang/az/shop.php`. Because these run from a queue worker with no request locale, setting it explicitly is what makes the translation correct rather than accidental.

- [ ] **Step 5: Run the tests and commit**

Run: `php artisan test --filter=OrderLocaleTest`
Expected: PASS, 3 tests.

Run: `php artisan test`
Expected: the whole suite green — the 175 from before this plan plus everything added since, with 2 skipped.

```bash
npm run build
git add resources app/Mail lang public/build tests
git commit -m "feat: style the confirmation and lookup pages, localise order emails"
```

- [ ] **Step 6: Drive the whole path once by hand**

Automated tests do not prove the shop feels right. Start it and walk the path a customer walks:

```bash
php artisan migrate --force
php artisan db:seed --class=DemoShopSeeder --force
php artisan serve --host=127.0.0.1 --port=8123
```

Then visit `/`, follow the redirect to `/en`, open a product, add it with a monogram, open the cart, check out with the mock gateway, and finally look the order up. Repeat on `/az`. Screenshot the catalogue and product page at 1280px and **look at them** — a page that passes its tests and still looks wrong is not done.

Report anything that reads as unfinished; small visual corrections belong in this task rather than a follow-up.

---

## Verification checklist

Before declaring the plan complete:

- [ ] `php artisan test` is green with 2 skips (the MySQL-only concurrency tests).
- [ ] `DB_CONNECTION=mysql_test php artisan test` is green with 0 skips.
- [ ] Plan 1's checkout tests pass without ever having been edited.
- [ ] `/` redirects to `/en`; `/az` renders Azerbaijani; `/de` is a 404.
- [ ] The schwa renders in the chosen serif on a real screenshot.
- [ ] `POST /payment/callback` still resolves without a locale prefix.
- [ ] A price appears nowhere except through `<x-price>`.
- [ ] `npm run build` has been run and `public/build` committed.
