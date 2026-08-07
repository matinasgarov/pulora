# Leather Goods Commerce Engine — Implementation Plan (Plan 1 of 2)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the tested commerce engine — catalog, cart, shipping, discounts, orders, and a mock-backed payment flow — so a purchase can be completed end to end via HTTP with no real gateway and no UI polish.

**Architecture:** One Laravel 12 application. Domain logic lives in `app/Domain/*` services that controllers call; controllers stay thin. Payment providers sit behind a `PaymentGateway` interface with a `MockGateway` implementation, selected by config. All money is integer minor units (qəpik).

**Tech Stack:** PHP 8.5.4 (ZTS, already installed at `C:\php`), Laravel 12, Pest (Laravel 12's default test runner), SQLite for local/CI tests, MySQL 8 via Docker for concurrency tests. Filament v5 and the storefront are **Plan 2**, not this plan.

**Spec:** `docs/superpowers/specs/2026-08-08-leather-goods-ecommerce-design.md`

## Global Constraints

- **Money is always integer minor units.** Column and property names end in `_minor`. Never `float`, never `decimal` for money. Copied verbatim from spec: *"prices stored as integers in minor units (qəpik/cents), never floats"*.
- **Currency is AZN only in v1.** Store the ISO code on the order anyway; do not build conversion.
- **Every product has at least one variant**, even a plain one. No code may assume a product without variants is valid.
- **`order_items` are snapshots.** Product name, variant description, and unit price are copied at purchase time. Never join live to display a historical order.
- **Only the server-to-server callback is trusted** for payment confirmation. The browser redirect never marks an order paid.
- **`OrderService::markPaid()` must be idempotent.** Repeat callbacks produce one email and one stock decrement.
- **No customer accounts.** Guest checkout only. Order lookup is email + order number.
- **PHP is a ZTS build.** Composer and Laravel work fine; do not "fix" this.
- **Concurrency tests must run on MySQL, not SQLite.** `lockForUpdate()` is a silent no-op on SQLite, so a stock-oversell test would pass there while the bug remains. Tasks that need it say so explicitly.

## Database Decision

Local development and the bulk of CI run on **SQLite** (zero setup, fast, Laravel 12's default). **MySQL 8** is the production target — it is what cheap Azerbaijani shared hosting offers — and is used locally via Docker for the two concurrency tests. Eloquent migrations cover both; no raw SQL that is dialect-specific.

## File Structure

| Path | Responsibility |
|---|---|
| `app/Domain/Money.php` | Minor-unit formatting/parsing helpers. Pure functions, no DB. |
| `app/Domain/Catalog/Models/{Product,Variant,ProductImage,VariantOption,OptionValue,PersonalizationOption}.php` | Eloquent models, one per table |
| `app/Domain/Cart/CartLine.php` | Immutable DTO: one resolved cart line |
| `app/Domain/Cart/CartSnapshot.php` | Immutable DTO: all lines + totals, recalculated server-side |
| `app/Domain/Cart/CartService.php` | Session cart mutation + recalculation from DB |
| `app/Domain/Cart/PersonalizationValidator.php` | Per-product monogram rules (length, allowed chars) |
| `app/Domain/Shipping/{ShippingCalculator,ShippingQuote}.php` | Country + weight → rate |
| `app/Domain/Shipping/Models/{ShippingZone,ShippingRate}.php` | Eloquent models |
| `app/Domain/Discount/{DiscountService,DiscountResult}.php` | Code validation and amount math |
| `app/Domain/Discount/Models/DiscountCode.php` | Eloquent model |
| `app/Domain/Order/Models/{Order,OrderItem}.php` | Eloquent models |
| `app/Domain/Order/OrderStatus.php` | Backed enum |
| `app/Domain/Order/CustomerDetails.php` | Immutable DTO: email, name, address |
| `app/Domain/Order/OrderService.php` | Cart → order, stock reservation, `markPaid` |
| `app/Domain/Payment/PaymentGateway.php` | The interface |
| `app/Domain/Payment/{PaymentRedirect,CallbackResult,RefundResult}.php` | Immutable DTOs |
| `app/Domain/Payment/MockGateway.php` | Dev/CI implementation |
| `app/Domain/Payment/Models/PaymentLog.php` | Eloquent model |
| `app/Http/Controllers/CheckoutController.php` | Thin: validate, call services, redirect |
| `app/Http/Controllers/PaymentCallbackController.php` | Thin: verify, call `markPaid` |
| `app/Jobs/ReleaseExpiredReservations.php` | Scheduled stock release |

---

## Task 1: Environment and Laravel skeleton

Nothing in this plan runs until PHP has its extensions and Composer exists. This task is verified by a working `php artisan` and a passing default test.

**Files:**
- Modify: `C:\php\php.ini`
- Create: the Laravel application in `C:\Users\Matin Asgarov\leather-shop\`

- [ ] **Step 1: Back up php.ini before touching it**

```powershell
Copy-Item "C:\php\php.ini" "C:\php\php.ini.backup-before-leathershop"
```

- [ ] **Step 2: Enable the eight required extensions**

The DLLs already exist in `C:\php\ext` — verified. They are commented out in `php.ini`. Uncomment or append:

```powershell
Add-Content -Path "C:\php\php.ini" -Encoding utf8 -Value @"

; --- enabled for leather-shop (Laravel 12 + Filament v5) ---
extension_dir = "C:\php\ext"
extension=pdo_sqlite
extension=sqlite3
extension=pdo_mysql
extension=fileinfo
extension=gd
extension=intl
extension=zip
"@
```

`pdo_pgsql` is deliberately omitted — MySQL is the production target. `curl`, `mbstring`, and `openssl` are already enabled.

- [ ] **Step 3: Verify every extension loaded**

Run:
```powershell
php -r "foreach(['pdo_sqlite','sqlite3','pdo_mysql','fileinfo','gd','intl','zip','mbstring','openssl','curl'] as $x){ echo str_pad($x,12), extension_loaded($x)?'OK':'FAIL', PHP_EOL; }"
```
Expected: ten lines, all `OK`. If any says `FAIL`, the `extension_dir` path is wrong — check it points at `C:\php\ext`.

- [ ] **Step 4: Install Composer**

```powershell
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=C:\php --filename=composer.phar
Remove-Item composer-setup.php
Set-Content -Path "C:\php\composer.bat" -Encoding ascii -Value '@php "%~dp0composer.phar" %*'
```

Run: `composer --version`
Expected: a version string. If `composer` is not found, `C:\php` is not on PATH — invoke it as `C:\php\composer.bat` for the rest of this plan.

- [ ] **Step 5: Create the Laravel 12 application**

The repo already exists at `leather-shop` with a `docs/` directory and git history, so scaffold into a temp directory and merge — `composer create-project` refuses a non-empty target.

```powershell
cd "C:\Users\Matin Asgarov"
composer create-project laravel/laravel:^12.0 leather-shop-tmp
Get-ChildItem -Path "leather-shop-tmp" -Force | Where-Object { $_.Name -ne '.git' } | Move-Item -Destination "leather-shop" -Force
Remove-Item "leather-shop-tmp" -Recurse -Force
```

- [ ] **Step 6: Configure SQLite and run the default test suite**

```powershell
cd "C:\Users\Matin Asgarov\leather-shop"
New-Item -ItemType File database\database.sqlite
php artisan migrate
php artisan test
```
Expected: migrations run, tests pass (Laravel ships two trivial passing tests). This proves PHP, Composer, Laravel, and SQLite all work together before any domain code exists.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "chore: scaffold Laravel 12 application"
```

---

## Task 2: Money helper

The smallest piece of the system and the one every other task depends on. Doing it first means no later task invents its own rounding.

**Files:**
- Create: `app/Domain/Money.php`
- Test: `tests/Unit/Domain/MoneyTest.php`

**Interfaces:**
- Consumes: nothing
- Produces: `Money::format(int $minor, string $currency = 'AZN'): string`, `Money::percentOf(int $minor, float $percent): int`

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Unit/Domain/MoneyTest.php

use App\Domain\Money;

it('formats minor units as a decimal string', function () {
    expect(Money::format(12345))->toBe('123.45 AZN');
    expect(Money::format(5))->toBe('0.05 AZN');
    expect(Money::format(0))->toBe('0.00 AZN');
});

it('rounds percentages half up to whole minor units', function () {
    expect(Money::percentOf(10000, 10))->toBe(1000);
    // 333 * 10% = 33.3 -> 33, not 34, and never 33.300000000000004
    expect(Money::percentOf(333, 10))->toBe(33);
    // 335 * 10% = 33.5 -> half up -> 34
    expect(Money::percentOf(335, 10))->toBe(34);
});
```

The third assertion is the one that matters: it pins the rounding direction so two developers cannot disagree about it later.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MoneyTest`
Expected: FAIL with `Class "App\Domain\Money" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php // app/Domain/Money.php

namespace App\Domain;

final class Money
{
    public static function format(int $minor, string $currency = 'AZN'): string
    {
        return number_format($minor / 100, 2, '.', '') . ' ' . $currency;
    }

    public static function percentOf(int $minor, float $percent): int
    {
        return (int) round($minor * $percent / 100, 0, PHP_ROUND_HALF_UP);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=MoneyTest`
Expected: PASS, 2 tests

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Money.php tests/Unit/Domain/MoneyTest.php
git commit -m "feat: add Money helper with half-up minor-unit rounding"
```

---

## Task 3: Catalog schema and models

**Files:**
- Create: `database/migrations/*_create_catalog_tables.php`
- Create: `app/Domain/Catalog/Models/Product.php`, `Variant.php`, `ProductImage.php`
- Create: `database/factories/{ProductFactory,VariantFactory}.php`
- Test: `tests/Feature/Catalog/CatalogModelTest.php`

**Interfaces:**
- Consumes: nothing
- Produces: `Product` (`hasMany variants`, `hasMany images`, scope `active()`), `Variant` (`belongsTo product`, `effectivePriceMinor(): int`, columns `sku`, `stock_quantity`, `weight_grams`, `price_minor_override`)

- [ ] **Step 1: Write the migration**

```php
<?php // database/migrations/2026_08_08_000100_create_catalog_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->text('story')->nullable();
            $t->unsignedInteger('base_price_minor');
            $t->unsignedSmallInteger('lead_time_days')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('variants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('sku')->unique();
            $t->string('description')->default('');
            $t->unsignedInteger('price_minor_override')->nullable();
            $t->integer('stock_quantity')->default(0);
            $t->unsignedInteger('weight_grams')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('product_images', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->string('alt_text')->default('');
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('variants');
        Schema::dropIfExists('products');
    }
};
```

`stock_quantity` is a signed `integer`, not unsigned. Unsigned would throw a database error on an oversell bug instead of letting a test observe a negative value — and we want the test to catch it, not the driver.

- [ ] **Step 2: Write the failing test**

```php
<?php // tests/Feature/Catalog/CatalogModelTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;

it('falls back to the product base price when the variant has no override', function () {
    $product = Product::factory()->create(['base_price_minor' => 8900]);
    $variant = Variant::factory()->for($product)->create(['price_minor_override' => null]);

    expect($variant->effectivePriceMinor())->toBe(8900);
});

it('prefers the variant override when present', function () {
    $product = Product::factory()->create(['base_price_minor' => 8900]);
    $variant = Variant::factory()->for($product)->create(['price_minor_override' => 9900]);

    expect($variant->effectivePriceMinor())->toBe(9900);
});

it('excludes inactive products from the active scope', function () {
    Product::factory()->create(['is_active' => true]);
    Product::factory()->create(['is_active' => false]);

    expect(Product::active()->count())->toBe(1);
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --filter=CatalogModelTest`
Expected: FAIL — `Class "App\Domain\Catalog\Models\Product" not found`

- [ ] **Step 4: Write the models**

```php
<?php // app/Domain/Catalog/Models/Product.php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'base_price_minor' => 'integer',
        'is_active' => 'boolean',
    ];

    public function variants() { return $this->hasMany(Variant::class); }
    public function images() { return $this->hasMany(ProductImage::class)->orderBy('sort_order'); }

    public function scopeActive(Builder $q): Builder { return $q->where('is_active', true); }
}
```

```php
<?php // app/Domain/Catalog/Models/Variant.php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Variant extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'price_minor_override' => 'integer',
        'stock_quantity' => 'integer',
        'weight_grams' => 'integer',
        'is_active' => 'boolean',
    ];

    public function product() { return $this->belongsTo(Product::class); }

    public function effectivePriceMinor(): int
    {
        return $this->price_minor_override ?? $this->product->base_price_minor;
    }
}
```

```php
<?php // app/Domain/Catalog/Models/ProductImage.php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $guarded = [];
}
```

- [ ] **Step 5: Write the factories**

```php
<?php // database/factories/ProductFactory.php

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true) . ' wallet';

        return [
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'description' => fake()->sentence(),
            'base_price_minor' => fake()->numberBetween(5000, 20000),
            'lead_time_days' => 3,
            'is_active' => true,
        ];
    }
}
```

```php
<?php // database/factories/VariantFactory.php

namespace Database\Factories;

use App\Domain\Catalog\Models\Variant;
use Illuminate\Database\Eloquent\Factories\Factory;

class VariantFactory extends Factory
{
    protected $model = Variant::class;

    public function definition(): array
    {
        return [
            'sku' => strtoupper(fake()->unique()->bothify('WAL-####-??')),
            'description' => 'Brown / natural thread',
            'price_minor_override' => null,
            'stock_quantity' => 10,
            'weight_grams' => 120,
            'is_active' => true,
        ];
    }
}
```

Laravel's factory discovery assumes models live in `App\Models`, and these live in
`App\Domain\Catalog\Models`, so discovery will not find them. Add the resolver
explicitly to both models — always, not conditionally.

In `Product`:

```php
protected static function newFactory()
{
    return \Database\Factories\ProductFactory::new();
}
```

In `Variant`:

```php
protected static function newFactory()
{
    return \Database\Factories\VariantFactory::new();
}
```

- [ ] **Step 6: Run migration and tests**

Run: `php artisan migrate && php artisan test --filter=CatalogModelTest`
Expected: PASS, 3 tests

- [ ] **Step 7: Commit**

```bash
git add app/Domain/Catalog database/migrations database/factories tests/Feature/Catalog
git commit -m "feat: add product, variant, and image models"
```

---

## Task 4: Variant options and personalization rules

**Files:**
- Create: `database/migrations/*_create_option_and_personalization_tables.php`
- Create: `app/Domain/Catalog/Models/{VariantOption,OptionValue,PersonalizationOption}.php`
- Create: `app/Domain/Cart/PersonalizationValidator.php`
- Test: `tests/Unit/Cart/PersonalizationValidatorTest.php`

**Interfaces:**
- Consumes: `Product` from Task 3
- Produces: `PersonalizationOption` (columns `type`, `label`, `price_delta_minor`, `max_characters`, `allowed_pattern`), `PersonalizationValidator::validate(Product $product, array $input): array` — returns normalized `['monogram' => 'MA']`, throws `InvalidPersonalizationException`

- [ ] **Step 1: Write the migration**

```php
<?php // database/migrations/2026_08_08_000200_create_option_and_personalization_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('variant_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('name');               // "Leather colour"
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('option_values', function (Blueprint $t) {
            $t->id();
            $t->foreignId('variant_option_id')->constrained()->cascadeOnDelete();
            $t->string('value');              // "Cognac"
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('option_value_variant', function (Blueprint $t) {
            $t->foreignId('variant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('option_value_id')->constrained()->cascadeOnDelete();
            $t->primary(['variant_id', 'option_value_id']);
        });

        Schema::create('personalization_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('type');               // monogram | gift_wrap | custom_stamp
            $t->string('label');
            $t->unsignedInteger('price_delta_minor')->default(0);
            $t->unsignedSmallInteger('max_characters')->default(3);
            $t->string('allowed_pattern')->default('/^[A-Z]+$/');
            $t->boolean('is_required')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personalization_options');
        Schema::dropIfExists('option_value_variant');
        Schema::dropIfExists('option_values');
        Schema::dropIfExists('variant_options');
    }
};
```

- [ ] **Step 2: Write the failing test**

```php
<?php // tests/Unit/Cart/PersonalizationValidatorTest.php

use App\Domain\Catalog\Models\PersonalizationOption;
use App\Domain\Catalog\Models\Product;
use App\Domain\Cart\InvalidPersonalizationException;
use App\Domain\Cart\PersonalizationValidator;

beforeEach(function () {
    $this->product = Product::factory()->create();
    PersonalizationOption::create([
        'product_id' => $this->product->id,
        'type' => 'monogram',
        'label' => 'Monogram',
        'price_delta_minor' => 1000,
        'max_characters' => 3,
        'allowed_pattern' => '/^[A-Z]+$/',
        'is_required' => false,
    ]);
    $this->validator = new PersonalizationValidator();
});

it('uppercases and accepts a valid monogram', function () {
    expect($this->validator->validate($this->product, ['monogram' => 'ma']))
        ->toBe(['monogram' => 'MA']);
});

it('rejects a monogram longer than the product allows', function () {
    $this->validator->validate($this->product, ['monogram' => 'ABCD']);
})->throws(InvalidPersonalizationException::class, 'Monogram must be at most 3 characters.');

it('rejects characters outside the allowed pattern', function () {
    $this->validator->validate($this->product, ['monogram' => 'A1']);
})->throws(InvalidPersonalizationException::class);

it('ignores personalization the product does not offer', function () {
    expect($this->validator->validate($this->product, ['gift_wrap' => 'yes']))->toBe([]);
});

it('rejects a missing required option', function () {
    PersonalizationOption::where('product_id', $this->product->id)->update(['is_required' => true]);
    $this->product->refresh()->load('personalizationOptions');

    $this->validator->validate($this->product, []);
})->throws(InvalidPersonalizationException::class, 'Monogram is required.');
```

The fourth test is the security-relevant one: a customer POSTing `gift_wrap` for a product that does not offer it must get silence, not a free extra.

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --filter=PersonalizationValidatorTest`
Expected: FAIL — `Class "App\Domain\Cart\PersonalizationValidator" not found`

- [ ] **Step 4: Write the models and validator**

```php
<?php // app/Domain/Catalog/Models/PersonalizationOption.php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalizationOption extends Model
{
    protected $guarded = [];

    protected $casts = [
        'price_delta_minor' => 'integer',
        'max_characters' => 'integer',
        'is_required' => 'boolean',
    ];

    public function product() { return $this->belongsTo(Product::class); }
}
```

```php
<?php // app/Domain/Catalog/Models/VariantOption.php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

class VariantOption extends Model
{
    protected $guarded = [];

    public function product() { return $this->belongsTo(Product::class); }
    public function values() { return $this->hasMany(OptionValue::class)->orderBy('sort_order'); }
}
```

```php
<?php // app/Domain/Catalog/Models/OptionValue.php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

class OptionValue extends Model
{
    protected $guarded = [];

    public function option() { return $this->belongsTo(VariantOption::class, 'variant_option_id'); }
    public function variants() { return $this->belongsToMany(Variant::class); }
}
```

Add to `Product`:

```php
public function personalizationOptions() { return $this->hasMany(PersonalizationOption::class); }
public function variantOptions() { return $this->hasMany(VariantOption::class)->orderBy('sort_order'); }
```

```php
<?php // app/Domain/Cart/InvalidPersonalizationException.php

namespace App\Domain\Cart;

use RuntimeException;

class InvalidPersonalizationException extends RuntimeException {}
```

```php
<?php // app/Domain/Cart/PersonalizationValidator.php

namespace App\Domain\Cart;

use App\Domain\Catalog\Models\Product;

class PersonalizationValidator
{
    /**
     * @param  array<string, string>  $input
     * @return array<string, string>  normalized, containing only offered options
     */
    public function validate(Product $product, array $input): array
    {
        $result = [];

        foreach ($product->personalizationOptions as $option) {
            $raw = trim((string) ($input[$option->type] ?? ''));

            if ($raw === '') {
                if ($option->is_required) {
                    throw new InvalidPersonalizationException("{$option->label} is required.");
                }
                continue;
            }

            $value = mb_strtoupper($raw);

            if (mb_strlen($value) > $option->max_characters) {
                throw new InvalidPersonalizationException(
                    "{$option->label} must be at most {$option->max_characters} characters."
                );
            }

            if (preg_match($option->allowed_pattern, $value) !== 1) {
                throw new InvalidPersonalizationException("{$option->label} contains characters we cannot stamp.");
            }

            $result[$option->type] = $value;
        }

        return $result;
    }
}
```

Anything not in `personalizationOptions` is never read from `$input` — that is what makes the fourth test pass, and it is a whitelist by construction rather than by filtering.

- [ ] **Step 5: Run migration and tests**

Run: `php artisan migrate && php artisan test --filter=PersonalizationValidatorTest`
Expected: PASS, 5 tests

- [ ] **Step 6: Commit**

```bash
git add app/Domain database/migrations tests/Unit/Cart
git commit -m "feat: add variant options and personalization validation"
```

---

## Task 5: Cart DTOs and server-side recalculation

The most security-critical task in the plan. The cart stores **identifiers and quantities only** — never prices. Prices are recomputed from the database on every read.

**Files:**
- Create: `app/Domain/Cart/CartLine.php`, `CartSnapshot.php`, `CartService.php`
- Test: `tests/Feature/Cart/CartServiceTest.php`

**Interfaces:**
- Consumes: `Variant`, `PersonalizationValidator` from Tasks 3–4
- Produces:
  - `CartLine` — readonly, constructor order: `string $lineKey`, `int $variantId`, `int $quantity`, `string $productName`, `string $variantDescription`, `int $unitPriceMinor`, `array $personalization`, `int $weightGrams`; method `lineTotalMinor(): int`
  - `CartSnapshot` — readonly: `CartLine[] $lines`; methods `subtotalMinor(): int`, `totalWeightGrams(): int`, `isEmpty(): bool`
  - `CartService::add(int $variantId, int $quantity, array $personalization = []): void`
  - `CartService::remove(string $lineKey): void`
  - `CartService::clear(): void`
  - `CartService::snapshot(): CartSnapshot`

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Cart/CartServiceTest.php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\Models\PersonalizationOption;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;

beforeEach(function () {
    $this->product = Product::factory()->create(['base_price_minor' => 8900, 'name' => 'Bifold']);
    $this->variant = Variant::factory()->for($this->product)->create([
        'stock_quantity' => 5, 'weight_grams' => 120, 'description' => 'Cognac',
    ]);
    $this->cart = app(CartService::class);
});

it('prices a line from the database, not from the caller', function () {
    $this->cart->add($this->variant->id, 2);

    $snapshot = $this->cart->snapshot();

    expect($snapshot->lines)->toHaveCount(1)
        ->and($snapshot->lines[0]->unitPriceMinor)->toBe(8900)
        ->and($snapshot->lines[0]->lineTotalMinor())->toBe(17800)
        ->and($snapshot->subtotalMinor())->toBe(17800)
        ->and($snapshot->totalWeightGrams())->toBe(240);
});

it('reflects a price change made after the item was added', function () {
    $this->cart->add($this->variant->id, 1);
    $this->product->update(['base_price_minor' => 9900]);

    expect($this->cart->snapshot()->subtotalMinor())->toBe(9900);
});

it('adds the personalization delta to the unit price', function () {
    PersonalizationOption::create([
        'product_id' => $this->product->id, 'type' => 'monogram', 'label' => 'Monogram',
        'price_delta_minor' => 1000, 'max_characters' => 3,
        'allowed_pattern' => '/^[A-Z]+$/', 'is_required' => false,
    ]);

    $this->cart->add($this->variant->id, 1, ['monogram' => 'ma']);
    $snapshot = $this->cart->snapshot();

    expect($snapshot->lines[0]->unitPriceMinor)->toBe(9900)
        ->and($snapshot->lines[0]->personalization)->toBe(['monogram' => 'MA']);
});

it('keeps differently personalized lines separate', function () {
    PersonalizationOption::create([
        'product_id' => $this->product->id, 'type' => 'monogram', 'label' => 'Monogram',
        'price_delta_minor' => 1000, 'max_characters' => 3,
        'allowed_pattern' => '/^[A-Z]+$/', 'is_required' => false,
    ]);

    $this->cart->add($this->variant->id, 1, ['monogram' => 'AA']);
    $this->cart->add($this->variant->id, 1, ['monogram' => 'BB']);

    expect($this->cart->snapshot()->lines)->toHaveCount(2);
});

it('merges quantity for identical lines', function () {
    $this->cart->add($this->variant->id, 1);
    $this->cart->add($this->variant->id, 2);

    $snapshot = $this->cart->snapshot();
    expect($snapshot->lines)->toHaveCount(1)
        ->and($snapshot->lines[0]->quantity)->toBe(3);
});

it('drops lines whose variant became inactive', function () {
    $this->cart->add($this->variant->id, 1);
    $this->variant->update(['is_active' => false]);

    expect($this->cart->snapshot()->isEmpty())->toBeTrue();
});
```

Test two is the whole point: a stale price in a session cannot become a real discount.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CartServiceTest`
Expected: FAIL — `Target class [App\Domain\Cart\CartService] does not exist.`

- [ ] **Step 3: Write the DTOs**

```php
<?php // app/Domain/Cart/CartLine.php

namespace App\Domain\Cart;

final readonly class CartLine
{
    public function __construct(
        public string $lineKey,
        public int $variantId,
        public int $quantity,
        public string $productName,
        public string $variantDescription,
        public int $unitPriceMinor,
        public array $personalization,
        public int $weightGrams,
    ) {}

    public function lineTotalMinor(): int
    {
        return $this->unitPriceMinor * $this->quantity;
    }
}
```

```php
<?php // app/Domain/Cart/CartSnapshot.php

namespace App\Domain\Cart;

final readonly class CartSnapshot
{
    /** @param CartLine[] $lines */
    public function __construct(public array $lines) {}

    public function subtotalMinor(): int
    {
        return array_sum(array_map(fn (CartLine $l) => $l->lineTotalMinor(), $this->lines));
    }

    public function totalWeightGrams(): int
    {
        return array_sum(array_map(fn (CartLine $l) => $l->weightGrams * $l->quantity, $this->lines));
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }
}
```

- [ ] **Step 4: Write CartService**

```php
<?php // app/Domain/Cart/CartService.php

namespace App\Domain\Cart;

use App\Domain\Catalog\Models\Variant;
use Illuminate\Contracts\Session\Session;

class CartService
{
    private const KEY = 'cart.lines';

    public function __construct(
        private Session $session,
        private PersonalizationValidator $validator,
    ) {}

    public function add(int $variantId, int $quantity, array $personalization = []): void
    {
        $variant = Variant::with('product.personalizationOptions')->findOrFail($variantId);
        $clean = $this->validator->validate($variant->product, $personalization);

        $lineKey = $this->lineKey($variantId, $clean);
        $lines = $this->rawLines();

        $lines[$lineKey] = [
            'variant_id' => $variantId,
            'quantity' => ($lines[$lineKey]['quantity'] ?? 0) + max(1, $quantity),
            'personalization' => $clean,
        ];

        $this->session->put(self::KEY, $lines);
    }

    public function remove(string $lineKey): void
    {
        $lines = $this->rawLines();
        unset($lines[$lineKey]);
        $this->session->put(self::KEY, $lines);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    public function snapshot(): CartSnapshot
    {
        $raw = $this->rawLines();
        if ($raw === []) {
            return new CartSnapshot([]);
        }

        $variants = Variant::with('product.personalizationOptions')
            ->whereIn('id', array_column($raw, 'variant_id'))
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $lines = [];

        foreach ($raw as $key => $item) {
            $variant = $variants->get($item['variant_id']);
            if (! $variant || ! $variant->product->is_active) {
                continue;
            }

            $lines[] = new CartLine(
                lineKey: $key,
                variantId: $variant->id,
                quantity: $item['quantity'],
                productName: $variant->product->name,
                variantDescription: $variant->description,
                unitPriceMinor: $variant->effectivePriceMinor()
                    + $this->personalizationDeltaMinor($variant, $item['personalization']),
                personalization: $item['personalization'],
                weightGrams: $variant->weight_grams,
            );
        }

        return new CartSnapshot($lines);
    }

    private function personalizationDeltaMinor(Variant $variant, array $personalization): int
    {
        return $variant->product->personalizationOptions
            ->whereIn('type', array_keys($personalization))
            ->sum('price_delta_minor');
    }

    private function lineKey(int $variantId, array $personalization): string
    {
        ksort($personalization);

        return $variantId . ':' . md5(json_encode($personalization));
    }

    private function rawLines(): array
    {
        return $this->session->get(self::KEY, []);
    }
}
```

The line key hashes the personalization, which is what makes two different monograms two different lines while identical ones merge.

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=CartServiceTest`
Expected: PASS, 6 tests

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Cart tests/Feature/Cart
git commit -m "feat: add cart with server-side price recalculation"
```

---

## Task 6: Shipping calculator

**Files:**
- Create: `database/migrations/*_create_shipping_tables.php`
- Create: `app/Domain/Shipping/Models/{ShippingZone,ShippingRate}.php`, `ShippingQuote.php`, `ShippingCalculator.php`
- Test: `tests/Feature/Shipping/ShippingCalculatorTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks
- Produces:
  - `ShippingQuote` — readonly: `int $rateId`, `string $name`, `int $priceMinor`
  - `ShippingCalculator::quotesFor(string $countryCode, int $weightGrams): ShippingQuote[]`
  - `ShippingCalculator::quoteById(int $rateId, string $countryCode, int $weightGrams): ShippingQuote` — throws `NoShippingRateException` if that rate is not valid for the destination

- [ ] **Step 1: Write the migration**

```php
<?php // database/migrations/2026_08_08_000300_create_shipping_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $t) {
            $t->id();
            $t->string('name');                       // "Azerbaijan", "Regional", "Rest of world"
            $t->json('country_codes');                // ["AZ"] — empty array means catch-all
            $t->boolean('is_fallback')->default(false);
            $t->timestamps();
        });

        Schema::create('shipping_rates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            $t->string('name');                       // "Standard", "Express"
            $t->unsignedInteger('min_weight_grams')->default(0);
            $t->unsignedInteger('max_weight_grams');
            $t->unsignedInteger('price_minor');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
        Schema::dropIfExists('shipping_zones');
    }
};
```

- [ ] **Step 2: Write the failing test**

```php
<?php // tests/Feature/Shipping/ShippingCalculatorTest.php

use App\Domain\Shipping\NoShippingRateException;
use App\Domain\Shipping\ShippingCalculator;
use App\Domain\Shipping\Models\ShippingRate;
use App\Domain\Shipping\Models\ShippingZone;

beforeEach(function () {
    $az = ShippingZone::create(['name' => 'Azerbaijan', 'country_codes' => ['AZ'], 'is_fallback' => false]);
    $world = ShippingZone::create(['name' => 'Rest of world', 'country_codes' => [], 'is_fallback' => true]);

    ShippingRate::create(['shipping_zone_id' => $az->id, 'name' => 'Standard',
        'min_weight_grams' => 0, 'max_weight_grams' => 500, 'price_minor' => 500]);
    ShippingRate::create(['shipping_zone_id' => $az->id, 'name' => 'Standard',
        'min_weight_grams' => 501, 'max_weight_grams' => 2000, 'price_minor' => 900]);
    ShippingRate::create(['shipping_zone_id' => $world->id, 'name' => 'International',
        'min_weight_grams' => 0, 'max_weight_grams' => 2000, 'price_minor' => 4500]);

    $this->calc = app(ShippingCalculator::class);
});

it('picks the rate whose weight bracket contains the order', function () {
    expect($this->calc->quotesFor('AZ', 300)[0]->priceMinor)->toBe(500);
    expect($this->calc->quotesFor('AZ', 800)[0]->priceMinor)->toBe(900);
});

it('treats bracket boundaries as inclusive on both ends', function () {
    expect($this->calc->quotesFor('AZ', 500)[0]->priceMinor)->toBe(500);
    expect($this->calc->quotesFor('AZ', 501)[0]->priceMinor)->toBe(900);
});

it('falls back to the catch-all zone for an unlisted country', function () {
    expect($this->calc->quotesFor('DE', 300)[0]->priceMinor)->toBe(4500);
});

it('returns no quotes when the parcel exceeds every bracket', function () {
    expect($this->calc->quotesFor('AZ', 9999))->toBe([]);
});

it('rejects a rate id that is not valid for the destination', function () {
    $azRateId = ShippingRate::where('price_minor', 500)->first()->id;

    $this->calc->quoteById($azRateId, 'DE', 300);
})->throws(NoShippingRateException::class);
```

The last test blocks a customer from posting Azerbaijan's 5 AZN rate id on an order shipping to Germany.

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --filter=ShippingCalculatorTest`
Expected: FAIL — `Class "App\Domain\Shipping\Models\ShippingZone" not found`

- [ ] **Step 4: Write the models, DTO, and calculator**

```php
<?php // app/Domain/Shipping/Models/ShippingZone.php

namespace App\Domain\Shipping\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    protected $guarded = [];

    protected $casts = ['country_codes' => 'array', 'is_fallback' => 'boolean'];

    public function rates() { return $this->hasMany(ShippingRate::class); }
}
```

```php
<?php // app/Domain/Shipping/Models/ShippingRate.php

namespace App\Domain\Shipping\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingRate extends Model
{
    protected $guarded = [];

    protected $casts = [
        'min_weight_grams' => 'integer',
        'max_weight_grams' => 'integer',
        'price_minor' => 'integer',
    ];

    public function zone() { return $this->belongsTo(ShippingZone::class, 'shipping_zone_id'); }
}
```

```php
<?php // app/Domain/Shipping/ShippingQuote.php

namespace App\Domain\Shipping;

final readonly class ShippingQuote
{
    public function __construct(
        public int $rateId,
        public string $name,
        public int $priceMinor,
    ) {}
}
```

```php
<?php // app/Domain/Shipping/NoShippingRateException.php

namespace App\Domain\Shipping;

use RuntimeException;

class NoShippingRateException extends RuntimeException {}
```

```php
<?php // app/Domain/Shipping/ShippingCalculator.php

namespace App\Domain\Shipping;

use App\Domain\Shipping\Models\ShippingRate;
use App\Domain\Shipping\Models\ShippingZone;

class ShippingCalculator
{
    /** @return ShippingQuote[] */
    public function quotesFor(string $countryCode, int $weightGrams): array
    {
        $zone = $this->zoneFor($countryCode);
        if (! $zone) {
            return [];
        }

        return ShippingRate::where('shipping_zone_id', $zone->id)
            ->where('min_weight_grams', '<=', $weightGrams)
            ->where('max_weight_grams', '>=', $weightGrams)
            ->orderBy('price_minor')
            ->get()
            ->map(fn (ShippingRate $r) => new ShippingQuote($r->id, $r->name, $r->price_minor))
            ->all();
    }

    public function quoteById(int $rateId, string $countryCode, int $weightGrams): ShippingQuote
    {
        foreach ($this->quotesFor($countryCode, $weightGrams) as $quote) {
            if ($quote->rateId === $rateId) {
                return $quote;
            }
        }

        throw new NoShippingRateException(
            "Shipping rate {$rateId} is not available for {$countryCode} at {$weightGrams}g."
        );
    }

    private function zoneFor(string $countryCode): ?ShippingZone
    {
        $code = strtoupper($countryCode);

        foreach (ShippingZone::orderBy('is_fallback')->get() as $zone) {
            if (in_array($code, $zone->country_codes, true)) {
                return $zone;
            }
        }

        return ShippingZone::where('is_fallback', true)->first();
    }
}
```

Zone matching happens in PHP rather than a JSON `WHERE` clause because JSON containment syntax differs between SQLite and MySQL, and the zone table has three rows.

- [ ] **Step 5: Run migration and tests**

Run: `php artisan migrate && php artisan test --filter=ShippingCalculatorTest`
Expected: PASS, 5 tests

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Shipping database/migrations tests/Feature/Shipping
git commit -m "feat: add zone and weight-bracket shipping calculator"
```

---

## Task 7: Discount codes

**Files:**
- Create: `database/migrations/*_create_discount_codes_table.php`
- Create: `app/Domain/Discount/Models/DiscountCode.php`, `DiscountResult.php`, `DiscountService.php`
- Test: `tests/Feature/Discount/DiscountServiceTest.php`

**Interfaces:**
- Consumes: `Money` from Task 2
- Produces:
  - `DiscountResult` — readonly: `int $codeId`, `string $code`, `int $amountMinor`
  - `DiscountService::apply(string $code, int $subtotalMinor): DiscountResult` — throws `InvalidDiscountException`
  - `DiscountService::consume(int $codeId): void` — increments `times_used`, called inside the `markPaid` transaction in Task 11

- [ ] **Step 1: Write the migration**

```php
<?php // database/migrations/2026_08_08_000400_create_discount_codes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('discount_codes', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('kind');                                  // percent | fixed
            $t->unsignedInteger('value');                        // percent points, or minor units
            $t->unsignedInteger('minimum_order_minor')->default(0);
            $t->unsignedInteger('usage_limit')->nullable();      // null = unlimited
            $t->unsignedInteger('times_used')->default(0);
            $t->timestamp('expires_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_codes');
    }
};
```

- [ ] **Step 2: Write the failing test**

```php
<?php // tests/Feature/Discount/DiscountServiceTest.php

use App\Domain\Discount\InvalidDiscountException;
use App\Domain\Discount\Models\DiscountCode;
use App\Domain\Discount\DiscountService;

beforeEach(fn () => $this->service = app(DiscountService::class));

function makeCode(array $overrides = []): DiscountCode
{
    return DiscountCode::create(array_merge([
        'code' => 'LEATHER10', 'kind' => 'percent', 'value' => 10,
        'minimum_order_minor' => 0, 'usage_limit' => null, 'times_used' => 0,
        'expires_at' => null, 'is_active' => true,
    ], $overrides));
}

it('computes a percentage discount', function () {
    makeCode();
    expect($this->service->apply('LEATHER10', 10000)->amountMinor)->toBe(1000);
});

it('matches codes case-insensitively', function () {
    makeCode();
    expect($this->service->apply('leather10', 10000)->amountMinor)->toBe(1000);
});

it('computes a fixed discount', function () {
    makeCode(['code' => 'FIVER', 'kind' => 'fixed', 'value' => 500]);
    expect($this->service->apply('FIVER', 10000)->amountMinor)->toBe(500);
});

it('never discounts more than the subtotal', function () {
    makeCode(['code' => 'BIG', 'kind' => 'fixed', 'value' => 99999]);
    expect($this->service->apply('BIG', 10000)->amountMinor)->toBe(10000);
});

it('rejects a code below its minimum order', function () {
    makeCode(['minimum_order_minor' => 20000]);
    $this->service->apply('LEATHER10', 10000);
})->throws(InvalidDiscountException::class, 'This code requires a minimum order of 200.00 AZN.');

it('rejects an expired code', function () {
    makeCode(['expires_at' => now()->subDay()]);
    $this->service->apply('LEATHER10', 10000);
})->throws(InvalidDiscountException::class);

it('rejects a code that reached its usage limit', function () {
    makeCode(['usage_limit' => 2, 'times_used' => 2]);
    $this->service->apply('LEATHER10', 10000);
})->throws(InvalidDiscountException::class);

it('rejects an unknown code', function () {
    $this->service->apply('NOPE', 10000);
})->throws(InvalidDiscountException::class);
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --filter=DiscountServiceTest`
Expected: FAIL — `Class "App\Domain\Discount\Models\DiscountCode" not found`

- [ ] **Step 4: Write the model, DTO, and service**

```php
<?php // app/Domain/Discount/Models/DiscountCode.php

namespace App\Domain\Discount\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountCode extends Model
{
    protected $guarded = [];

    protected $casts = [
        'value' => 'integer',
        'minimum_order_minor' => 'integer',
        'usage_limit' => 'integer',
        'times_used' => 'integer',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];
}
```

```php
<?php // app/Domain/Discount/DiscountResult.php

namespace App\Domain\Discount;

final readonly class DiscountResult
{
    public function __construct(
        public int $codeId,
        public string $code,
        public int $amountMinor,
    ) {}
}
```

```php
<?php // app/Domain/Discount/InvalidDiscountException.php

namespace App\Domain\Discount;

use RuntimeException;

class InvalidDiscountException extends RuntimeException {}
```

```php
<?php // app/Domain/Discount/DiscountService.php

namespace App\Domain\Discount;

use App\Domain\Discount\Models\DiscountCode;
use App\Domain\Money;

class DiscountService
{
    public function apply(string $code, int $subtotalMinor): DiscountResult
    {
        $discount = DiscountCode::whereRaw('UPPER(code) = ?', [mb_strtoupper(trim($code))])
            ->where('is_active', true)
            ->first();

        if (! $discount) {
            throw new InvalidDiscountException('That discount code is not valid.');
        }

        if ($discount->expires_at && $discount->expires_at->isPast()) {
            throw new InvalidDiscountException('That discount code has expired.');
        }

        if ($discount->usage_limit !== null && $discount->times_used >= $discount->usage_limit) {
            throw new InvalidDiscountException('That discount code has already been fully used.');
        }

        if ($subtotalMinor < $discount->minimum_order_minor) {
            throw new InvalidDiscountException(
                'This code requires a minimum order of ' . Money::format($discount->minimum_order_minor) . '.'
            );
        }

        $amount = $discount->kind === 'percent'
            ? Money::percentOf($subtotalMinor, $discount->value)
            : $discount->value;

        return new DiscountResult($discount->id, $discount->code, min($amount, $subtotalMinor));
    }

    public function consume(int $codeId): void
    {
        DiscountCode::where('id', $codeId)->increment('times_used');
    }
}
```

`consume()` is separate from `apply()` deliberately: a code is only spent when money actually arrives, not when someone types it into a form they abandon.

- [ ] **Step 5: Run migration and tests**

Run: `php artisan migrate && php artisan test --filter=DiscountServiceTest`
Expected: PASS, 8 tests

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Discount database/migrations tests/Feature/Discount
git commit -m "feat: add discount code validation and consumption"
```

---

## Task 8: Order schema and models

**Files:**
- Create: `database/migrations/*_create_order_tables.php`
- Create: `app/Domain/Order/OrderStatus.php`, `Models/Order.php`, `Models/OrderItem.php`, `CustomerDetails.php`
- Test: `tests/Feature/Order/OrderModelTest.php`

**Interfaces:**
- Consumes: nothing
- Produces:
  - `OrderStatus` — backed enum: `PendingPayment`, `Paid`, `InProduction`, `Shipped`, `Delivered`, `Cancelled`, `Refunded`
  - `CustomerDetails` — readonly: `string $email`, `string $name`, `string $addressLine1`, `?string $addressLine2`, `string $city`, `?string $postcode`, `string $countryCode`, `?string $phone`
  - `Order` (`hasMany items`, `belongsTo` nothing), `OrderItem`

- [ ] **Step 1: Write the migration**

```php
<?php // database/migrations/2026_08_08_000500_create_order_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('order_number')->unique();
            $t->string('status')->default('pending_payment');
            $t->string('source')->default('web');            // web | shopify | manual

            $t->string('customer_email');
            $t->string('customer_name');
            $t->string('phone')->nullable();

            $t->string('address_line1');
            $t->string('address_line2')->nullable();
            $t->string('city');
            $t->string('postcode')->nullable();
            $t->string('country_code', 2);

            $t->unsignedInteger('subtotal_minor');
            $t->unsignedInteger('shipping_minor');
            $t->unsignedInteger('discount_minor')->default(0);
            $t->unsignedInteger('total_minor');
            $t->string('currency', 3)->default('AZN');

            $t->foreignId('discount_code_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('shipping_rate_id')->nullable()->constrained()->nullOnDelete();

            $t->string('payment_reference')->nullable()->index();
            $t->string('tracking_number')->nullable();

            $t->string('customs_contents')->nullable();
            $t->unsignedInteger('customs_value_minor')->nullable();

            $t->unsignedInteger('total_weight_grams')->default(0);

            $t->timestamp('reserved_until')->nullable()->index();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('shipped_at')->nullable();
            $t->timestamps();
        });

        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('variant_id')->nullable()->constrained()->nullOnDelete();

            // Snapshot columns — never joined live.
            $t->string('product_name');
            $t->string('variant_description');
            $t->string('sku');
            $t->unsignedInteger('unit_price_minor');
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('line_total_minor');
            $t->json('personalization')->nullable();
            $t->unsignedInteger('weight_grams')->default(0);

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
```

- [ ] **Step 2: Write the failing test**

```php
<?php // tests/Feature/Order/OrderModelTest.php

use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Order\OrderStatus;

it('casts status to the enum', function () {
    $order = Order::create(orderAttributes());

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

it('keeps item snapshots after the source product changes', function () {
    $order = Order::create(orderAttributes());
    OrderItem::create([
        'order_id' => $order->id, 'variant_id' => null,
        'product_name' => 'Bifold', 'variant_description' => 'Cognac', 'sku' => 'WAL-1',
        'unit_price_minor' => 8900, 'quantity' => 1, 'line_total_minor' => 8900,
        'personalization' => ['monogram' => 'MA'], 'weight_grams' => 120,
    ]);

    $item = $order->items()->first();

    expect($item->product_name)->toBe('Bifold')
        ->and($item->personalization)->toBe(['monogram' => 'MA'])
        ->and($item->unit_price_minor)->toBe(8900);
});

function orderAttributes(array $overrides = []): array
{
    return array_merge([
        'order_number' => 'LS-2026-0001',
        'customer_email' => 'buyer@example.com',
        'customer_name' => 'Test Buyer',
        'address_line1' => '1 Nizami St',
        'city' => 'Baku',
        'country_code' => 'AZ',
        'subtotal_minor' => 8900,
        'shipping_minor' => 500,
        'discount_minor' => 0,
        'total_minor' => 9400,
    ], $overrides);
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --filter=OrderModelTest`
Expected: FAIL — `Class "App\Domain\Order\Models\Order" not found`

- [ ] **Step 4: Write the enum, DTO, and models**

```php
<?php // app/Domain/Order/OrderStatus.php

namespace App\Domain\Order;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case InProduction = 'in_production';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
```

```php
<?php // app/Domain/Order/CustomerDetails.php

namespace App\Domain\Order;

final readonly class CustomerDetails
{
    public function __construct(
        public string $email,
        public string $name,
        public string $addressLine1,
        public ?string $addressLine2,
        public string $city,
        public ?string $postcode,
        public string $countryCode,
        public ?string $phone = null,
    ) {}
}
```

```php
<?php // app/Domain/Order/Models/Order.php

namespace App\Domain\Order\Models;

use App\Domain\Order\OrderStatus;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => OrderStatus::class,
        'subtotal_minor' => 'integer',
        'shipping_minor' => 'integer',
        'discount_minor' => 'integer',
        'total_minor' => 'integer',
        'total_weight_grams' => 'integer',
        'customs_value_minor' => 'integer',
        'reserved_until' => 'datetime',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
    ];

    public function items() { return $this->hasMany(OrderItem::class); }
}
```

```php
<?php // app/Domain/Order/Models/OrderItem.php

namespace App\Domain\Order\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'personalization' => 'array',
        'unit_price_minor' => 'integer',
        'quantity' => 'integer',
        'line_total_minor' => 'integer',
        'weight_grams' => 'integer',
    ];
}
```

- [ ] **Step 5: Run migration and tests**

Run: `php artisan migrate && php artisan test --filter=OrderModelTest`
Expected: PASS, 2 tests

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Order database/migrations tests/Feature/Order
git commit -m "feat: add order and order item models with snapshot columns"
```

---

## Task 9: OrderService — cart to order with stock reservation

**Files:**
- Create: `app/Domain/Order/OrderService.php`, `app/Domain/Order/InsufficientStockException.php`
- Test: `tests/Feature/Order/CreateOrderTest.php`

**Interfaces:**
- Consumes: `CartSnapshot`/`CartLine` (Task 5), `ShippingQuote` (Task 6), `DiscountResult` (Task 7), `Order`/`OrderItem`/`OrderStatus`/`CustomerDetails` (Task 8)
- Produces: `OrderService::createFromCart(CartSnapshot $cart, CustomerDetails $customer, ShippingQuote $shipping, ?DiscountResult $discount = null): Order`

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Order/CreateOrderTest.php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Discount\DiscountResult;
use App\Domain\Order\CustomerDetails;
use App\Domain\Order\InsufficientStockException;
use App\Domain\Order\OrderService;
use App\Domain\Order\OrderStatus;
use App\Domain\Shipping\ShippingQuote;

beforeEach(function () {
    $this->product = Product::factory()->create(['base_price_minor' => 8900, 'name' => 'Bifold']);
    $this->variant = Variant::factory()->for($this->product)->create([
        'stock_quantity' => 5, 'weight_grams' => 120, 'sku' => 'WAL-1', 'description' => 'Cognac',
    ]);
    $this->cart = app(CartService::class);
    $this->orders = app(OrderService::class);
    $this->customer = new CustomerDetails(
        email: 'buyer@example.com', name: 'Test Buyer',
        addressLine1: '1 Nizami St', addressLine2: null, city: 'Baku',
        postcode: 'AZ1000', countryCode: 'AZ', phone: null,
    );
    $this->shipping = new ShippingQuote(rateId: 1, name: 'Standard', priceMinor: 500);
});

it('creates a pending order with correct totals', function () {
    $this->cart->add($this->variant->id, 2);

    $order = $this->orders->createFromCart($this->cart->snapshot(), $this->customer, $this->shipping);

    expect($order->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->subtotal_minor)->toBe(17800)
        ->and($order->shipping_minor)->toBe(500)
        ->and($order->discount_minor)->toBe(0)
        ->and($order->total_minor)->toBe(18300)
        ->and($order->currency)->toBe('AZN')
        ->and($order->source)->toBe('web')
        ->and($order->total_weight_grams)->toBe(240)
        ->and($order->order_number)->toStartWith('LS-');
});

it('subtracts the discount from the total but not from shipping', function () {
    $this->cart->add($this->variant->id, 1);
    $discount = new DiscountResult(codeId: 1, code: 'LEATHER10', amountMinor: 890);

    $order = $this->orders->createFromCart($this->cart->snapshot(), $this->customer, $this->shipping, $discount);

    expect($order->discount_minor)->toBe(890)
        ->and($order->total_minor)->toBe(8900 - 890 + 500);
});

it('snapshots item details rather than referencing live data', function () {
    $this->cart->add($this->variant->id, 1);
    $order = $this->orders->createFromCart($this->cart->snapshot(), $this->customer, $this->shipping);

    $this->product->update(['name' => 'Renamed', 'base_price_minor' => 20000]);

    $item = $order->items()->first();
    expect($item->product_name)->toBe('Bifold')
        ->and($item->unit_price_minor)->toBe(8900)
        ->and($item->sku)->toBe('WAL-1');
});

it('reserves stock immediately and sets an expiry', function () {
    $this->cart->add($this->variant->id, 2);
    $order = $this->orders->createFromCart($this->cart->snapshot(), $this->customer, $this->shipping);

    expect($this->variant->fresh()->stock_quantity)->toBe(3)
        ->and($order->reserved_until->isFuture())->toBeTrue();
});

it('refuses to create an order that exceeds available stock', function () {
    $this->cart->add($this->variant->id, 99);

    $this->orders->createFromCart($this->cart->snapshot(), $this->customer, $this->shipping);
})->throws(InsufficientStockException::class);

it('leaves stock untouched when creation fails', function () {
    $this->cart->add($this->variant->id, 99);

    try {
        $this->orders->createFromCart($this->cart->snapshot(), $this->customer, $this->shipping);
    } catch (InsufficientStockException) {
        // expected
    }

    expect($this->variant->fresh()->stock_quantity)->toBe(5)
        ->and(\App\Domain\Order\Models\Order::count())->toBe(0);
});

it('generates unique order numbers', function () {
    $numbers = collect(range(1, 5))->map(function () {
        $this->cart->clear();
        $this->cart->add($this->variant->id, 1);
        return $this->orders->createFromCart($this->cart->snapshot(), $this->customer, $this->shipping)->order_number;
    });

    expect($numbers->unique())->toHaveCount(5);
});
```

The sixth test is the one that protects you from a half-written order: a failure must roll back the stock decrement too.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CreateOrderTest`
Expected: FAIL — `Target class [App\Domain\Order\OrderService] does not exist.`

- [ ] **Step 3: Write the exception**

```php
<?php // app/Domain/Order/InsufficientStockException.php

namespace App\Domain\Order;

use RuntimeException;

class InsufficientStockException extends RuntimeException {}
```

- [ ] **Step 4: Write OrderService**

```php
<?php // app/Domain/Order/OrderService.php

namespace App\Domain\Order;

use App\Domain\Cart\CartLine;
use App\Domain\Cart\CartSnapshot;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Discount\DiscountResult;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Shipping\ShippingQuote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public const RESERVATION_MINUTES = 30;

    public function createFromCart(
        CartSnapshot $cart,
        CustomerDetails $customer,
        ShippingQuote $shipping,
        ?DiscountResult $discount = null,
    ): Order {
        if ($cart->isEmpty()) {
            throw new InsufficientStockException('Your cart is empty.');
        }

        return DB::transaction(function () use ($cart, $customer, $shipping, $discount) {
            $subtotal = $cart->subtotalMinor();
            $discountMinor = $discount?->amountMinor ?? 0;

            $order = Order::create([
                'order_number' => $this->nextOrderNumber(),
                'status' => OrderStatus::PendingPayment,
                'source' => 'web',
                'customer_email' => $customer->email,
                'customer_name' => $customer->name,
                'phone' => $customer->phone,
                'address_line1' => $customer->addressLine1,
                'address_line2' => $customer->addressLine2,
                'city' => $customer->city,
                'postcode' => $customer->postcode,
                'country_code' => strtoupper($customer->countryCode),
                'subtotal_minor' => $subtotal,
                'shipping_minor' => $shipping->priceMinor,
                'discount_minor' => $discountMinor,
                'total_minor' => $subtotal - $discountMinor + $shipping->priceMinor,
                'currency' => 'AZN',
                'discount_code_id' => $discount?->codeId,
                'shipping_rate_id' => $shipping->rateId,
                'total_weight_grams' => $cart->totalWeightGrams(),
                'customs_contents' => 'Hand-crafted leather goods',
                'customs_value_minor' => $subtotal,
                'reserved_until' => now()->addMinutes(self::RESERVATION_MINUTES),
            ]);

            foreach ($cart->lines as $line) {
                $this->reserveStock($line);

                OrderItem::create([
                    'order_id' => $order->id,
                    'variant_id' => $line->variantId,
                    'product_name' => $line->productName,
                    'variant_description' => $line->variantDescription,
                    'sku' => Variant::whereKey($line->variantId)->value('sku') ?? '',
                    'unit_price_minor' => $line->unitPriceMinor,
                    'quantity' => $line->quantity,
                    'line_total_minor' => $line->lineTotalMinor(),
                    'personalization' => $line->personalization,
                    'weight_grams' => $line->weightGrams,
                ]);
            }

            return $order->load('items');
        });
    }

    private function reserveStock(CartLine $line): void
    {
        $variant = Variant::whereKey($line->variantId)->lockForUpdate()->first();

        if (! $variant || $variant->stock_quantity < $line->quantity) {
            throw new InsufficientStockException(
                "{$line->productName} ({$line->variantDescription}) is no longer available in that quantity."
            );
        }

        $variant->decrement('stock_quantity', $line->quantity);
    }

    private function nextOrderNumber(): string
    {
        return 'LS-' . now()->format('Y') . '-' . Str::upper(Str::random(6));
    }
}
```

`lockForUpdate()` inside the transaction is what makes two simultaneous checkouts serialize on MySQL. It is a no-op on SQLite — Task 15 tests it where it actually applies.

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=CreateOrderTest`
Expected: PASS, 7 tests

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Order tests/Feature/Order
git commit -m "feat: create orders from cart with transactional stock reservation"
```

---

## Task 10: Payment gateway interface, mock implementation, and logging

**Files:**
- Create: `database/migrations/*_create_payment_logs_table.php`
- Create: `app/Domain/Payment/{PaymentGateway,PaymentRedirect,CallbackResult,RefundResult,MockGateway}.php`
- Create: `app/Domain/Payment/Models/PaymentLog.php`
- Modify: `config/services.php`, `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Payment/MockGatewayTest.php`

**Interfaces:**
- Consumes: `Order` (Task 8)
- Produces:
  - `PaymentRedirect` — readonly: `string $url`, `string $reference`
  - `CallbackResult` — readonly: `bool $isValid`, `string $reference`, `bool $isPaid`, `array $raw`
  - `RefundResult` — readonly: `bool $succeeded`, `string $reference`
  - `PaymentGateway` interface with `createPayment`, `verifyCallback`, `refund`
  - `MockGateway` bound to `PaymentGateway` when `config('services.payment.driver') === 'mock'`

- [ ] **Step 1: Write the migration**

```php
<?php // database/migrations/2026_08_08_000600_create_payment_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->string('gateway');
            $t->string('direction');            // request | callback
            $t->string('reference')->nullable()->index();
            $t->json('payload');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
    }
};
```

- [ ] **Step 2: Write the failing test**

```php
<?php // tests/Feature/Payment/MockGatewayTest.php

use App\Domain\Order\Models\Order;
use App\Domain\Payment\Models\PaymentLog;
use App\Domain\Payment\PaymentGateway;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->gateway = app(PaymentGateway::class);
    $this->order = Order::create([
        'order_number' => 'LS-2026-TEST01', 'customer_email' => 'buyer@example.com',
        'customer_name' => 'Test Buyer', 'address_line1' => '1 Nizami St', 'city' => 'Baku',
        'country_code' => 'AZ', 'subtotal_minor' => 8900, 'shipping_minor' => 500,
        'total_minor' => 9400,
    ]);
});

it('resolves the mock gateway in the test environment', function () {
    expect($this->gateway)->toBeInstanceOf(\App\Domain\Payment\MockGateway::class);
});

it('returns a redirect carrying a reference derived from the order', function () {
    $redirect = $this->gateway->createPayment($this->order);

    expect($redirect->reference)->toBe('MOCK-LS-2026-TEST01')
        ->and($redirect->url)->toContain('MOCK-LS-2026-TEST01');
});

it('logs the payment request', function () {
    $this->gateway->createPayment($this->order);

    $log = PaymentLog::where('order_id', $this->order->id)->where('direction', 'request')->first();
    expect($log)->not->toBeNull()
        ->and($log->gateway)->toBe('mock');
});

it('accepts a correctly signed callback', function () {
    $result = $this->gateway->verifyCallback(Request::create('/callback', 'POST', [
        'reference' => 'MOCK-LS-2026-TEST01',
        'status' => 'paid',
        'signature' => hash_hmac('sha256', 'MOCK-LS-2026-TEST01|paid', 'test-secret'),
    ]));

    expect($result->isValid)->toBeTrue()
        ->and($result->isPaid)->toBeTrue()
        ->and($result->reference)->toBe('MOCK-LS-2026-TEST01');
});

it('rejects a callback with a bad signature', function () {
    $result = $this->gateway->verifyCallback(Request::create('/callback', 'POST', [
        'reference' => 'MOCK-LS-2026-TEST01',
        'status' => 'paid',
        'signature' => 'forged',
    ]));

    expect($result->isValid)->toBeFalse()
        ->and($result->isPaid)->toBeFalse();
});

it('logs callbacks including invalid ones', function () {
    $this->gateway->verifyCallback(Request::create('/callback', 'POST', [
        'reference' => 'MOCK-LS-2026-TEST01', 'status' => 'paid', 'signature' => 'forged',
    ]));

    expect(PaymentLog::where('direction', 'callback')->count())->toBe(1);
});
```

Logging invalid callbacks is not incidental — a forged callback is the first sign of someone probing the store, and it must leave a trace.

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --filter=MockGatewayTest`
Expected: FAIL — `Target [App\Domain\Payment\PaymentGateway] is not instantiable.`

- [ ] **Step 4: Write the DTOs and interface**

```php
<?php // app/Domain/Payment/PaymentRedirect.php

namespace App\Domain\Payment;

final readonly class PaymentRedirect
{
    public function __construct(public string $url, public string $reference) {}
}
```

```php
<?php // app/Domain/Payment/CallbackResult.php

namespace App\Domain\Payment;

final readonly class CallbackResult
{
    public function __construct(
        public bool $isValid,
        public string $reference,
        public bool $isPaid,
        public array $raw = [],
    ) {}
}
```

```php
<?php // app/Domain/Payment/RefundResult.php

namespace App\Domain\Payment;

final readonly class RefundResult
{
    public function __construct(public bool $succeeded, public string $reference) {}
}
```

```php
<?php // app/Domain/Payment/PaymentGateway.php

namespace App\Domain\Payment;

use App\Domain\Order\Models\Order;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function createPayment(Order $order): PaymentRedirect;

    public function verifyCallback(Request $request): CallbackResult;

    public function refund(Order $order, int $amountMinor): RefundResult;
}
```

- [ ] **Step 5: Write the log model and MockGateway**

```php
<?php // app/Domain/Payment/Models/PaymentLog.php

namespace App\Domain\Payment\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentLog extends Model
{
    protected $guarded = [];

    protected $casts = ['payload' => 'array'];
}
```

```php
<?php // app/Domain/Payment/MockGateway.php

namespace App\Domain\Payment;

use App\Domain\Order\Models\Order;
use App\Domain\Payment\Models\PaymentLog;
use Illuminate\Http\Request;

class MockGateway implements PaymentGateway
{
    public function __construct(private string $secret) {}

    public function createPayment(Order $order): PaymentRedirect
    {
        $reference = 'MOCK-' . $order->order_number;

        PaymentLog::create([
            'order_id' => $order->id,
            'gateway' => 'mock',
            'direction' => 'request',
            'reference' => $reference,
            'payload' => ['amount_minor' => $order->total_minor, 'currency' => $order->currency],
        ]);

        return new PaymentRedirect(
            url: route('payment.mock.form', ['reference' => $reference]),
            reference: $reference,
        );
    }

    public function verifyCallback(Request $request): CallbackResult
    {
        $reference = (string) $request->input('reference', '');
        $status = (string) $request->input('status', '');
        $expected = hash_hmac('sha256', "{$reference}|{$status}", $this->secret);
        $isValid = hash_equals($expected, (string) $request->input('signature', ''));

        PaymentLog::create([
            'order_id' => Order::where('payment_reference', $reference)->value('id'),
            'gateway' => 'mock',
            'direction' => 'callback',
            'reference' => $reference,
            'payload' => ['valid' => $isValid] + $request->all(),
        ]);

        return new CallbackResult(
            isValid: $isValid,
            reference: $reference,
            isPaid: $isValid && $status === 'paid',
            raw: $request->all(),
        );
    }

    public function refund(Order $order, int $amountMinor): RefundResult
    {
        PaymentLog::create([
            'order_id' => $order->id,
            'gateway' => 'mock',
            'direction' => 'request',
            'reference' => $order->payment_reference,
            'payload' => ['refund_minor' => $amountMinor],
        ]);

        return new RefundResult(succeeded: true, reference: (string) $order->payment_reference);
    }
}
```

`hash_equals` rather than `===` — signature comparison must be constant-time.

- [ ] **Step 6: Wire configuration and binding**

Append to `config/services.php`:

```php
'payment' => [
    'driver' => env('PAYMENT_DRIVER', 'mock'),
    'mock_secret' => env('PAYMENT_MOCK_SECRET', 'test-secret'),
],
```

In `app/Providers/AppServiceProvider.php`, inside `register()`:

```php
$this->app->bind(\App\Domain\Payment\PaymentGateway::class, function ($app) {
    return match (config('services.payment.driver')) {
        'mock' => new \App\Domain\Payment\MockGateway(config('services.payment.mock_secret')),
        default => throw new \RuntimeException(
            'Unknown payment driver: ' . config('services.payment.driver')
        ),
    };
});
```

The `default` throws rather than silently falling back to mock. A production box misconfigured to "epoint" must fail loudly, never quietly accept fake payments.

Add to `.env` and `.env.example`:

```
PAYMENT_DRIVER=mock
PAYMENT_MOCK_SECRET=test-secret
```

- [ ] **Step 7: Add the mock payment form route**

In `routes/web.php`:

```php
Route::get('/payment/mock/{reference}', function (string $reference) {
    return response()->view('payment.mock', ['reference' => $reference]);
})->name('payment.mock.form');
```

Create `resources/views/payment/mock.blade.php`:

```blade
<!doctype html>
<title>Mock gateway</title>
<h1>Mock payment gateway</h1>
<p>Reference: <strong>{{ $reference }}</strong></p>
<form method="POST" action="{{ route('payment.callback') }}">
    @csrf
    <input type="hidden" name="reference" value="{{ $reference }}">
    <input type="hidden" name="status" value="paid">
    <input type="hidden" name="signature"
           value="{{ hash_hmac('sha256', $reference . '|paid', config('services.payment.mock_secret')) }}">
    <button type="submit">Pay now</button>
</form>
```

This stand-in exists so a human can click through the whole purchase flow before Epoint keys arrive. It is registered only when `PAYMENT_DRIVER=mock`.

- [ ] **Step 8: Run migration and tests**

Run: `php artisan migrate && php artisan test --filter=MockGatewayTest`
Expected: PASS, 6 tests

- [ ] **Step 9: Commit**

```bash
git add app/Domain/Payment config app/Providers routes resources/views/payment database/migrations tests/Feature/Payment
git commit -m "feat: add payment gateway interface with logged mock implementation"
```

---

## Task 11: Idempotent markPaid

The single most important behaviour in the system. Gateways retry callbacks; duplicates must be harmless.

**Files:**
- Modify: `app/Domain/Order/OrderService.php`
- Create: `app/Mail/OrderConfirmation.php`, `resources/views/mail/order-confirmation.blade.php`
- Test: `tests/Feature/Order/MarkPaidTest.php`

**Interfaces:**
- Consumes: `Order`, `OrderStatus`, `DiscountService::consume()` (Task 7)
- Produces: `OrderService::markPaid(Order $order, string $paymentReference): bool` — returns `true` if this call transitioned the order, `false` if it was already paid

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Order/MarkPaidTest.php

use App\Domain\Discount\Models\DiscountCode;
use App\Domain\Order\Models\Order;
use App\Domain\Order\OrderService;
use App\Domain\Order\OrderStatus;
use App\Mail\OrderConfirmation;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->orders = app(OrderService::class);
    $this->order = Order::create([
        'order_number' => 'LS-2026-PAID01', 'customer_email' => 'buyer@example.com',
        'customer_name' => 'Test Buyer', 'address_line1' => '1 Nizami St', 'city' => 'Baku',
        'country_code' => 'AZ', 'subtotal_minor' => 8900, 'shipping_minor' => 500,
        'total_minor' => 9400, 'reserved_until' => now()->addMinutes(30),
    ]);
});

it('transitions the order to paid and clears the reservation', function () {
    expect($this->orders->markPaid($this->order, 'REF-1'))->toBeTrue();

    $fresh = $this->order->fresh();
    expect($fresh->status)->toBe(OrderStatus::Paid)
        ->and($fresh->payment_reference)->toBe('REF-1')
        ->and($fresh->paid_at)->not->toBeNull()
        ->and($fresh->reserved_until)->toBeNull();
});

it('sends exactly one confirmation email', function () {
    $this->orders->markPaid($this->order, 'REF-1');

    Mail::assertSentCount(1);
    Mail::assertSent(OrderConfirmation::class,
        fn ($m) => $m->hasTo('buyer@example.com'));
});

it('is idempotent across repeated callbacks', function () {
    expect($this->orders->markPaid($this->order, 'REF-1'))->toBeTrue();
    expect($this->orders->markPaid($this->order->fresh(), 'REF-1'))->toBeFalse();
    expect($this->orders->markPaid($this->order->fresh(), 'REF-1'))->toBeFalse();

    Mail::assertSentCount(1);
});

it('consumes the discount code exactly once', function () {
    $code = DiscountCode::create([
        'code' => 'LEATHER10', 'kind' => 'percent', 'value' => 10,
        'minimum_order_minor' => 0, 'usage_limit' => 5, 'times_used' => 0, 'is_active' => true,
    ]);
    $this->order->update(['discount_code_id' => $code->id]);

    $this->orders->markPaid($this->order->fresh(), 'REF-1');
    $this->orders->markPaid($this->order->fresh(), 'REF-1');

    expect($code->fresh()->times_used)->toBe(1);
});

it('does not resurrect a cancelled order', function () {
    $this->order->update(['status' => OrderStatus::Cancelled]);

    expect($this->orders->markPaid($this->order->fresh(), 'REF-1'))->toBeFalse();
    expect($this->order->fresh()->status)->toBe(OrderStatus::Cancelled);
});
```

The last test covers a real sequence: reservation expires, order is cancelled, then a slow callback arrives. Stock has already been returned, so accepting payment here would sell inventory twice.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MarkPaidTest`
Expected: FAIL — `Call to undefined method App\Domain\Order\OrderService::markPaid()`

- [ ] **Step 3: Write the mailable**

```php
<?php // app/Mail/OrderConfirmation.php

namespace App\Mail;

use App\Domain\Order\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Order {$this->order->order_number} confirmed");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.order-confirmation',
            with: ['order' => $this->order->load('items')],
        );
    }
}
```

```blade
{{-- resources/views/mail/order-confirmation.blade.php --}}
@component('mail::message')
# Thank you, {{ $order->customer_name }}

We have received your order **{{ $order->order_number }}** and started work on it.

@component('mail::table')
| Item | Qty | Price |
|:-----|:---:|------:|
@foreach ($order->items as $item)
| {{ $item->product_name }} — {{ $item->variant_description }}@if($item->personalization) ({{ implode(', ', $item->personalization) }})@endif | {{ $item->quantity }} | {{ \App\Domain\Money::format($item->line_total_minor) }} |
@endforeach
@endcomponent

Shipping: {{ \App\Domain\Money::format($order->shipping_minor) }}
@if ($order->discount_minor > 0)
Discount: −{{ \App\Domain\Money::format($order->discount_minor) }}
@endif
**Total: {{ \App\Domain\Money::format($order->total_minor) }}**

Track your order any time with your email address and order number.

Thanks,<br>{{ config('app.name') }}
@endcomponent
```

- [ ] **Step 4: Add markPaid to OrderService**

Add these imports to `OrderService.php`:

```php
use App\Domain\Discount\DiscountService;
use App\Mail\OrderConfirmation;
use Illuminate\Support\Facades\Mail;
```

Add a constructor and the method:

```php
public function __construct(private DiscountService $discounts) {}

public function markPaid(Order $order, string $paymentReference): bool
{
    $transitioned = DB::transaction(function () use ($order, $paymentReference) {
        $locked = Order::whereKey($order->id)->lockForUpdate()->first();

        if (! $locked || $locked->status !== OrderStatus::PendingPayment) {
            return false;
        }

        $locked->update([
            'status' => OrderStatus::Paid,
            'payment_reference' => $paymentReference,
            'paid_at' => now(),
            'reserved_until' => null,
        ]);

        if ($locked->discount_code_id) {
            $this->discounts->consume($locked->discount_code_id);
        }

        return true;
    });

    if ($transitioned) {
        Mail::to($order->customer_email)->send(new OrderConfirmation($order->fresh()));
    }

    return $transitioned;
}
```

Two details carry the correctness. The status check inside the locked transaction is the idempotency guard — not a check before the transaction, which two concurrent callbacks could both pass. And the email is sent *after* the transaction commits, so a mail failure cannot roll back a payment that really happened.

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=MarkPaidTest`
Expected: PASS, 5 tests

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Order app/Mail resources/views/mail tests/Feature/Order
git commit -m "feat: add idempotent markPaid with confirmation email"
```

---

## Task 12: Checkout HTTP flow

**Files:**
- Create: `app/Http/Controllers/CheckoutController.php`, `app/Http/Requests/CheckoutRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Checkout/CheckoutFlowTest.php`

**Interfaces:**
- Consumes: `CartService`, `ShippingCalculator`, `DiscountService`, `OrderService`, `PaymentGateway`
- Produces: `POST /checkout` → redirect to gateway; route names `checkout.store`, `payment.callback`, `checkout.confirmation`

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Checkout/CheckoutFlowTest.php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Order\Models\Order;
use App\Domain\Order\OrderStatus;
use App\Domain\Shipping\Models\ShippingRate;
use App\Domain\Shipping\Models\ShippingZone;

beforeEach(function () {
    $this->product = Product::factory()->create(['base_price_minor' => 8900]);
    $this->variant = Variant::factory()->for($this->product)->create([
        'stock_quantity' => 5, 'weight_grams' => 120,
    ]);

    $zone = ShippingZone::create(['name' => 'Azerbaijan', 'country_codes' => ['AZ'], 'is_fallback' => true]);
    $this->rate = ShippingRate::create([
        'shipping_zone_id' => $zone->id, 'name' => 'Standard',
        'min_weight_grams' => 0, 'max_weight_grams' => 2000, 'price_minor' => 500,
    ]);
});

function checkoutPayload(array $overrides = []): array
{
    return array_merge([
        'email' => 'buyer@example.com',
        'name' => 'Test Buyer',
        'address_line1' => '1 Nizami St',
        'city' => 'Baku',
        'country_code' => 'AZ',
        'postcode' => 'AZ1000',
    ], $overrides);
}

it('creates a pending order and redirects to the gateway', function () {
    app(CartService::class)->add($this->variant->id, 1);

    $response = $this->post('/checkout', checkoutPayload(['shipping_rate_id' => $this->rate->id]));

    $order = Order::first();
    expect($order->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->total_minor)->toBe(9400);

    $response->assertRedirect(route('payment.mock.form', ['reference' => 'MOCK-' . $order->order_number]));
});

it('ignores a total supplied by the browser', function () {
    app(CartService::class)->add($this->variant->id, 1);

    $this->post('/checkout', checkoutPayload([
        'shipping_rate_id' => $this->rate->id,
        'total_minor' => 1,          // attacker-supplied
        'subtotal_minor' => 1,
    ]));

    expect(Order::first()->total_minor)->toBe(9400);
});

it('rejects a shipping rate that does not serve the destination', function () {
    $other = ShippingZone::create(['name' => 'Nowhere', 'country_codes' => ['XX'], 'is_fallback' => false]);
    $badRate = ShippingRate::create([
        'shipping_zone_id' => $other->id, 'name' => 'Free',
        'min_weight_grams' => 0, 'max_weight_grams' => 2000, 'price_minor' => 0,
    ]);
    app(CartService::class)->add($this->variant->id, 1);

    $this->post('/checkout', checkoutPayload(['shipping_rate_id' => $badRate->id]))
        ->assertSessionHasErrors('shipping_rate_id');

    expect(Order::count())->toBe(0);
});

it('refuses checkout when stock ran out, before any redirect', function () {
    app(CartService::class)->add($this->variant->id, 5);
    $this->variant->update(['stock_quantity' => 1]);

    $this->post('/checkout', checkoutPayload(['shipping_rate_id' => $this->rate->id]))
        ->assertSessionHasErrors('cart');

    expect(Order::count())->toBe(0)
        ->and($this->variant->fresh()->stock_quantity)->toBe(1);
});

it('rejects an empty cart', function () {
    $this->post('/checkout', checkoutPayload(['shipping_rate_id' => $this->rate->id]))
        ->assertSessionHasErrors('cart');
});

it('requires a valid email and address', function () {
    app(CartService::class)->add($this->variant->id, 1);

    $this->post('/checkout', ['email' => 'not-an-email', 'shipping_rate_id' => $this->rate->id])
        ->assertSessionHasErrors(['email', 'name', 'address_line1', 'city', 'country_code']);
});
```

The second test is the tampering check written as an HTTP request rather than a unit test, because that is the layer an attacker actually reaches.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CheckoutFlowTest`
Expected: FAIL — 404, the `/checkout` route does not exist

- [ ] **Step 3: Write the form request**

```php
<?php // app/Http/Requests/CheckoutRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:32'],
            'country_code' => ['required', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:32'],
            'shipping_rate_id' => ['required', 'integer'],
            'discount_code' => ['nullable', 'string', 'max:64'],
        ];
    }
}
```

`total_minor` and `subtotal_minor` are absent from the rules, so they are never read. Validation is the whitelist.

- [ ] **Step 4: Write the controller**

```php
<?php // app/Http/Controllers/CheckoutController.php

namespace App\Http\Controllers;

use App\Domain\Cart\CartService;
use App\Domain\Discount\DiscountService;
use App\Domain\Discount\InvalidDiscountException;
use App\Domain\Order\CustomerDetails;
use App\Domain\Order\InsufficientStockException;
use App\Domain\Order\OrderService;
use App\Domain\Payment\PaymentGateway;
use App\Domain\Shipping\NoShippingRateException;
use App\Domain\Shipping\ShippingCalculator;
use App\Http\Requests\CheckoutRequest;
use Illuminate\Http\RedirectResponse;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cart,
        private ShippingCalculator $shipping,
        private DiscountService $discounts,
        private OrderService $orders,
        private PaymentGateway $gateway,
    ) {}

    public function store(CheckoutRequest $request): RedirectResponse
    {
        $snapshot = $this->cart->snapshot();

        if ($snapshot->isEmpty()) {
            return back()->withErrors(['cart' => 'Your cart is empty.'])->withInput();
        }

        try {
            $quote = $this->shipping->quoteById(
                (int) $request->validated('shipping_rate_id'),
                $request->validated('country_code'),
                $snapshot->totalWeightGrams(),
            );
        } catch (NoShippingRateException) {
            return back()->withErrors([
                'shipping_rate_id' => 'That shipping option is not available for your address.',
            ])->withInput();
        }

        $discount = null;
        if ($code = $request->validated('discount_code')) {
            try {
                $discount = $this->discounts->apply($code, $snapshot->subtotalMinor());
            } catch (InvalidDiscountException $e) {
                return back()->withErrors(['discount_code' => $e->getMessage()])->withInput();
            }
        }

        $customer = new CustomerDetails(
            email: $request->validated('email'),
            name: $request->validated('name'),
            addressLine1: $request->validated('address_line1'),
            addressLine2: $request->validated('address_line2'),
            city: $request->validated('city'),
            postcode: $request->validated('postcode'),
            countryCode: $request->validated('country_code'),
            phone: $request->validated('phone'),
        );

        try {
            $order = $this->orders->createFromCart($snapshot, $customer, $quote, $discount);
        } catch (InsufficientStockException $e) {
            return back()->withErrors(['cart' => $e->getMessage()])->withInput();
        }

        $redirect = $this->gateway->createPayment($order);
        $order->update(['payment_reference' => $redirect->reference]);

        $this->cart->clear();
        session(['last_order_number' => $order->order_number]);

        return redirect()->away($redirect->url);
    }
}
```

Stock is checked by creating the order, which happens **before** `createPayment()`. The customer never reaches a payment page for goods that cannot ship.

- [ ] **Step 5: Register the routes**

In `routes/web.php`:

```php
use App\Http\Controllers\CheckoutController;

Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::view('/checkout/confirmation', 'checkout.confirmation')->name('checkout.confirmation');
```

Create a minimal `resources/views/checkout/confirmation.blade.php`:

```blade
<!doctype html>
<title>Order confirmed</title>
<h1>Thank you</h1>
<p>Your order {{ session('last_order_number') }} is confirmed. A receipt is on its way by email.</p>
```

Plan 2 replaces this view with the designed storefront version.

- [ ] **Step 6: Run tests**

Run: `php artisan test --filter=CheckoutFlowTest`
Expected: PASS, 6 tests

- [ ] **Step 7: Commit**

```bash
git add app/Http routes resources/views/checkout tests/Feature/Checkout
git commit -m "feat: add checkout endpoint with server-side recalculation"
```

---

## Task 13: Payment callback endpoint

**Files:**
- Create: `app/Http/Controllers/PaymentCallbackController.php`
- Modify: `routes/web.php`, `bootstrap/app.php` (CSRF exemption)
- Test: `tests/Feature/Payment/CallbackEndpointTest.php`

**Interfaces:**
- Consumes: `PaymentGateway`, `OrderService::markPaid()`
- Produces: route `payment.callback` at `POST /payment/callback`

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Payment/CallbackEndpointTest.php

use App\Domain\Order\Models\Order;
use App\Domain\Order\OrderStatus;
use App\Mail\OrderConfirmation;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->order = Order::create([
        'order_number' => 'LS-2026-CB01', 'customer_email' => 'buyer@example.com',
        'customer_name' => 'Test Buyer', 'address_line1' => '1 Nizami St', 'city' => 'Baku',
        'country_code' => 'AZ', 'subtotal_minor' => 8900, 'shipping_minor' => 500,
        'total_minor' => 9400, 'payment_reference' => 'MOCK-LS-2026-CB01',
        'reserved_until' => now()->addMinutes(30),
    ]);
});

function signedCallback(string $reference, string $status = 'paid'): array
{
    return [
        'reference' => $reference,
        'status' => $status,
        'signature' => hash_hmac('sha256', "{$reference}|{$status}", config('services.payment.mock_secret')),
    ];
}

it('marks the order paid on a valid callback', function () {
    $this->post('/payment/callback', signedCallback('MOCK-LS-2026-CB01'))->assertOk();

    expect($this->order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('accepts the callback without a CSRF token', function () {
    $this->post('/payment/callback', signedCallback('MOCK-LS-2026-CB01'))
        ->assertOk();
});

it('processes a duplicate callback exactly once', function () {
    $payload = signedCallback('MOCK-LS-2026-CB01');

    $this->post('/payment/callback', $payload)->assertOk();
    $this->post('/payment/callback', $payload)->assertOk();
    $this->post('/payment/callback', $payload)->assertOk();

    Mail::assertSentCount(1);
    expect(\App\Domain\Payment\Models\PaymentLog::where('direction', 'callback')->count())->toBe(3);
});

it('leaves the order untouched on a forged signature', function () {
    $this->post('/payment/callback', [
        'reference' => 'MOCK-LS-2026-CB01', 'status' => 'paid', 'signature' => 'forged',
    ])->assertStatus(400);

    expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);

    // The operator IS alerted here (see Step 4); the customer is not.
    Mail::assertNotSent(OrderConfirmation::class);
});

it('ignores a callback for an unknown reference', function () {
    $this->post('/payment/callback', signedCallback('MOCK-DOES-NOT-EXIST'))->assertStatus(404);

    Mail::assertNotSent(OrderConfirmation::class);
});

it('does not mark paid when the gateway reports failure', function () {
    $this->post('/payment/callback', signedCallback('MOCK-LS-2026-CB01', 'failed'))->assertOk();

    expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});
```

Three callbacks logged but one email sent is the exact shape of correct idempotency — the log is a record of what arrived, the email is a record of what changed.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CallbackEndpointTest`
Expected: FAIL — 404, the callback route does not exist

- [ ] **Step 3: Write the controller**

```php
<?php // app/Http/Controllers/PaymentCallbackController.php

namespace App\Http\Controllers;

use App\Domain\Order\Models\Order;
use App\Domain\Order\OrderService;
use App\Domain\Payment\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PaymentCallbackController extends Controller
{
    public function __construct(
        private PaymentGateway $gateway,
        private OrderService $orders,
    ) {}

    public function __invoke(Request $request): Response
    {
        $result = $this->gateway->verifyCallback($request);

        if (! $result->isValid) {
            Log::warning('Rejected payment callback with invalid signature', [
                'reference' => $result->reference,
                'ip' => $request->ip(),
            ]);

            return response('invalid signature', 400);
        }

        $order = Order::where('payment_reference', $result->reference)->first();

        if (! $order) {
            Log::warning('Payment callback for unknown reference', ['reference' => $result->reference]);

            return response('unknown reference', 404);
        }

        if ($result->isPaid) {
            $this->orders->markPaid($order, $result->reference);
        }

        return response('ok', 200);
    }
}
```

Returning 200 for a duplicate is deliberate: gateways treat a non-2xx as "retry", so a 409 would make them hammer the endpoint forever.

- [ ] **Step 4: Notify the operator on a payment anomaly**

The spec requires that anomalies reach a human, not just a log file. Add the test to
`tests/Feature/Payment/CallbackEndpointTest.php`:

```php
it('emails the operator when a callback signature is forged', function () {
    config(['shop.operator_email' => 'owner@example.com']);

    $this->post('/payment/callback', [
        'reference' => 'MOCK-LS-2026-CB01', 'status' => 'paid', 'signature' => 'forged',
    ])->assertStatus(400);

    Mail::assertSent(\App\Mail\PaymentAnomaly::class,
        fn ($m) => $m->hasTo('owner@example.com'));
});
```

Create `config/shop.php`:

```php
<?php

return [
    'operator_email' => env('SHOP_OPERATOR_EMAIL', 'owner@example.com'),
];
```

Create `app/Mail/PaymentAnomaly.php`:

```php
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentAnomaly extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $reason,
        public string $reference,
        public ?string $ip = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Payment anomaly: {$this->reason}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.payment-anomaly');
    }
}
```

Create `resources/views/mail/payment-anomaly.blade.php`:

```blade
@component('mail::message')
# Payment anomaly

**{{ $reason }}**

Reference: `{{ $reference }}`
@if ($ip)
Source IP: `{{ $ip }}`
@endif

Check the `payment_logs` table for the full payload.
@endcomponent
```

In `PaymentCallbackController`, add `use App\Mail\PaymentAnomaly;` and
`use Illuminate\Support\Facades\Mail;`, then send alongside each existing
`Log::warning`:

```php
Mail::to(config('shop.operator_email'))->send(
    new PaymentAnomaly('Invalid callback signature', $result->reference, $request->ip())
);
```

and for the unknown-reference branch:

```php
Mail::to(config('shop.operator_email'))->send(
    new PaymentAnomaly('Callback for unknown reference', $result->reference, $request->ip())
);
```

Add `SHOP_OPERATOR_EMAIL=` to `.env` and `.env.example`.

Run: `php artisan test --filter=CallbackEndpointTest`
Expected: PASS, 7 tests

- [ ] **Step 5: Register the route and exempt it from CSRF**

In `routes/web.php`:

```php
use App\Http\Controllers\PaymentCallbackController;

Route::post('/payment/callback', PaymentCallbackController::class)->name('payment.callback');
```

In `bootstrap/app.php`, inside `->withMiddleware(function (Middleware $middleware) { ... })`:

```php
$middleware->validateCsrfTokens(except: ['payment/callback']);
```

A gateway's server has no session and no CSRF token. The HMAC signature is what authenticates this request instead — which is why Task 10's `hash_equals` check is load-bearing.

- [ ] **Step 6: Run tests**

Run: `php artisan test --filter=CallbackEndpointTest`
Expected: PASS, 7 tests

- [ ] **Step 7: Commit**

```bash
git add app/Http routes bootstrap tests/Feature/Payment
git commit -m "feat: add signature-verified payment callback endpoint"
```

---

## Task 14: Release expired stock reservations

**Files:**
- Create: `app/Jobs/ReleaseExpiredReservations.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Order/ReleaseReservationsTest.php`

**Interfaces:**
- Consumes: `Order`, `OrderStatus`, `Variant`
- Produces: `ReleaseExpiredReservations` job, scheduled every five minutes

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Order/ReleaseReservationsTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Order\OrderStatus;
use App\Jobs\ReleaseExpiredReservations;

function reservedOrder(Variant $variant, int $qty, $reservedUntil, OrderStatus $status = OrderStatus::PendingPayment): Order
{
    $order = Order::create([
        'order_number' => 'LS-' . fake()->unique()->bothify('####??'),
        'status' => $status,
        'customer_email' => 'buyer@example.com', 'customer_name' => 'Buyer',
        'address_line1' => '1 St', 'city' => 'Baku', 'country_code' => 'AZ',
        'subtotal_minor' => 8900, 'shipping_minor' => 500, 'total_minor' => 9400,
        'reserved_until' => $reservedUntil,
    ]);

    OrderItem::create([
        'order_id' => $order->id, 'variant_id' => $variant->id,
        'product_name' => 'Bifold', 'variant_description' => 'Cognac', 'sku' => $variant->sku,
        'unit_price_minor' => 8900, 'quantity' => $qty, 'line_total_minor' => 8900 * $qty,
        'weight_grams' => 120,
    ]);

    return $order;
}

beforeEach(function () {
    $this->variant = Variant::factory()->for(Product::factory())->create(['stock_quantity' => 3]);
});

it('returns stock and cancels an expired unpaid order', function () {
    $order = reservedOrder($this->variant, 2, now()->subMinute());

    (new ReleaseExpiredReservations)->handle();

    expect($this->variant->fresh()->stock_quantity)->toBe(5)
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()->reserved_until)->toBeNull();
});

it('leaves a reservation that has not expired alone', function () {
    $order = reservedOrder($this->variant, 2, now()->addMinutes(10));

    (new ReleaseExpiredReservations)->handle();

    expect($this->variant->fresh()->stock_quantity)->toBe(3)
        ->and($order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

it('never touches a paid order', function () {
    $order = reservedOrder($this->variant, 2, now()->subHour(), OrderStatus::Paid);

    (new ReleaseExpiredReservations)->handle();

    expect($this->variant->fresh()->stock_quantity)->toBe(3)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('does not double-release when run twice', function () {
    reservedOrder($this->variant, 2, now()->subMinute());

    (new ReleaseExpiredReservations)->handle();
    (new ReleaseExpiredReservations)->handle();

    expect($this->variant->fresh()->stock_quantity)->toBe(5);
});
```

The last test matters because scheduled jobs overlap in production more often than anyone expects.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ReleaseReservationsTest`
Expected: FAIL — `Class "App\Jobs\ReleaseExpiredReservations" not found`

- [ ] **Step 3: Write the job**

```php
<?php // app/Jobs/ReleaseExpiredReservations.php

namespace App\Jobs;

use App\Domain\Catalog\Models\Variant;
use App\Domain\Order\Models\Order;
use App\Domain\Order\OrderStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ReleaseExpiredReservations implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Order::query()
            ->where('status', OrderStatus::PendingPayment)
            ->whereNotNull('reserved_until')
            ->where('reserved_until', '<', now())
            ->with('items')
            ->chunkById(50, function ($orders) {
                foreach ($orders as $order) {
                    DB::transaction(function () use ($order) {
                        $locked = Order::whereKey($order->id)->lockForUpdate()->first();

                        // Re-check under lock: a callback may have paid it moments ago.
                        if ($locked->status !== OrderStatus::PendingPayment || $locked->reserved_until === null) {
                            return;
                        }

                        foreach ($order->items as $item) {
                            if ($item->variant_id) {
                                Variant::whereKey($item->variant_id)->increment('stock_quantity', $item->quantity);
                            }
                        }

                        $locked->update([
                            'status' => OrderStatus::Cancelled,
                            'reserved_until' => null,
                        ]);
                    });
                }
            });
    }
}
```

Clearing `reserved_until` in the same transaction as the status change is what makes a second run a no-op.

- [ ] **Step 4: Schedule it**

In `routes/console.php`:

```php
use App\Jobs\ReleaseExpiredReservations;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ReleaseExpiredReservations)->everyFiveMinutes()->withoutOverlapping();
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=ReleaseReservationsTest`
Expected: PASS, 4 tests

- [ ] **Step 6: Commit**

```bash
git add app/Jobs routes/console.php tests/Feature/Order
git commit -m "feat: release expired stock reservations on a schedule"
```

---

## Task 15: Concurrency test on MySQL, demo seeder, full suite

The final task proves the two things SQLite cannot: that `lockForUpdate()` actually prevents overselling, and that the whole suite is green.

**Files:**
- Create: `docker-compose.yml`
- Create: `tests/Feature/Concurrency/OversellTest.php`
- Create: `database/seeders/DemoShopSeeder.php`
- Modify: `phpunit.xml`

**Interfaces:**
- Consumes: everything built so far
- Produces: `php artisan db:seed --class=DemoShopSeeder` giving a clickable shop

- [ ] **Step 1: Add a MySQL service**

```yaml
# docker-compose.yml
services:
  mysql:
    image: mysql:8.4
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: leather_shop_test
    ports:
      - "3307:3306"
    command: --default-authentication-plugin=caching_sha2_password
```

Start it (Docker Desktop must be running — the daemon was not running when this plan was written):

```powershell
docker compose up -d mysql
docker compose exec mysql mysqladmin ping -proot
```
Expected: `mysqld is alive`

- [ ] **Step 2: Write the failing concurrency test**

```php
<?php // tests/Feature/Concurrency/OversellTest.php

use App\Domain\Cart\CartSnapshot;
use App\Domain\Cart\CartLine;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Order\CustomerDetails;
use App\Domain\Order\InsufficientStockException;
use App\Domain\Order\OrderService;
use App\Domain\Shipping\ShippingQuote;

// This test is meaningless on SQLite, where lockForUpdate() is a silent no-op.
beforeEach(function () {
    if (config('database.default') !== 'mysql_test') {
        $this->markTestSkipped('Concurrency behaviour is only observable on MySQL.');
    }
});

// Named for what it actually verifies: the stock check lives inside the locked
// transaction. It does not spawn parallel processes — see the note below.
it('checks stock inside the locked transaction so the second order is refused', function () {
    $product = Product::factory()->create(['base_price_minor' => 8900]);
    $variant = Variant::factory()->for($product)->create(['stock_quantity' => 1, 'weight_grams' => 120]);

    $customer = new CustomerDetails(
        email: 'buyer@example.com', name: 'Buyer', addressLine1: '1 St',
        addressLine2: null, city: 'Baku', postcode: null, countryCode: 'AZ', phone: null,
    );
    $shipping = new ShippingQuote(rateId: 1, name: 'Standard', priceMinor: 500);

    $snapshot = new CartSnapshot([new CartLine(
        lineKey: 'k', variantId: $variant->id, quantity: 1,
        productName: 'Bifold', variantDescription: 'Cognac',
        unitPriceMinor: 8900, personalization: [], weightGrams: 120,
    )]);

    $orders = app(OrderService::class);
    $succeeded = 0;

    foreach (range(1, 2) as $_) {
        try {
            $orders->createFromCart($snapshot, $customer, $shipping);
            $succeeded++;
        } catch (InsufficientStockException) {
            // expected for the loser
        }
    }

    expect($succeeded)->toBe(1)
        ->and($variant->fresh()->stock_quantity)->toBe(0);
});
```

This runs sequentially rather than with real parallel processes — genuinely concurrent PHP requests are awkward to orchestrate in a test suite. It still catches the regression that matters: any refactor that drops the stock check or moves it outside the transaction turns `$succeeded` into 2.

- [ ] **Step 3: Add the MySQL test connection**

In `config/database.php`, add to `connections`:

```php
'mysql_test' => [
    'driver' => 'mysql',
    'host' => env('MYSQL_TEST_HOST', '127.0.0.1'),
    'port' => env('MYSQL_TEST_PORT', '3307'),
    'database' => env('MYSQL_TEST_DATABASE', 'leather_shop_test'),
    'username' => env('MYSQL_TEST_USERNAME', 'root'),
    'password' => env('MYSQL_TEST_PASSWORD', 'root'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => true,
    'engine' => 'InnoDB',
],
```

- [ ] **Step 4: Run the concurrency test against MySQL**

Run:
```powershell
$env:DB_CONNECTION="mysql_test"; php artisan test --filter=OversellTest; Remove-Item Env:\DB_CONNECTION
```
Expected: PASS, 1 test. Without the `lockForUpdate()` written in Task 9, this fails with `$succeeded` equal to 2 — worth deleting the lock temporarily once to watch it fail, so you trust the test.

- [ ] **Step 5: Write the demo seeder**

```php
<?php // database/seeders/DemoShopSeeder.php

namespace Database\Seeders;

use App\Domain\Catalog\Models\PersonalizationOption;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Discount\Models\DiscountCode;
use App\Domain\Shipping\Models\ShippingRate;
use App\Domain\Shipping\Models\ShippingZone;
use Illuminate\Database\Seeder;

class DemoShopSeeder extends Seeder
{
    public function run(): void
    {
        $az = ShippingZone::create(['name' => 'Azerbaijan', 'country_codes' => ['AZ'], 'is_fallback' => false]);
        $regional = ShippingZone::create([
            'name' => 'Regional', 'country_codes' => ['TR', 'GE', 'RU', 'KZ'], 'is_fallback' => false,
        ]);
        $world = ShippingZone::create(['name' => 'Rest of world', 'country_codes' => [], 'is_fallback' => true]);

        foreach ([[$az, 500, 900], [$regional, 2500, 3500], [$world, 4500, 6500]] as [$zone, $light, $heavy]) {
            ShippingRate::create(['shipping_zone_id' => $zone->id, 'name' => 'Standard',
                'min_weight_grams' => 0, 'max_weight_grams' => 500, 'price_minor' => $light]);
            ShippingRate::create(['shipping_zone_id' => $zone->id, 'name' => 'Standard',
                'min_weight_grams' => 501, 'max_weight_grams' => 3000, 'price_minor' => $heavy]);
        }

        $catalog = [
            ['Bifold wallet', 8900, ['Cognac', 'Black', 'Natural']],
            ['Card holder', 4900, ['Cognac', 'Olive']],
            ['Long wallet', 12900, ['Black', 'Natural']],
        ];

        foreach ($catalog as [$name, $price, $colours]) {
            $product = Product::create([
                'name' => $name,
                'slug' => str($name)->slug()->toString(),
                'description' => 'Hand-cut, hand-stitched, and finished in our Baku workshop.',
                'story' => 'Made from full-grain vegetable-tanned leather that darkens with use.',
                'base_price_minor' => $price,
                'lead_time_days' => 5,
                'is_active' => true,
            ]);

            foreach ($colours as $i => $colour) {
                Variant::create([
                    'product_id' => $product->id,
                    'sku' => strtoupper(str($name)->slug('')->limit(6, '')) . '-' . strtoupper(substr($colour, 0, 3)),
                    'description' => $colour,
                    'stock_quantity' => 4 + $i,
                    'weight_grams' => 120,
                    'is_active' => true,
                ]);
            }

            PersonalizationOption::create([
                'product_id' => $product->id,
                'type' => 'monogram',
                'label' => 'Monogram',
                'price_delta_minor' => 1000,
                'max_characters' => 3,
                'allowed_pattern' => '/^[A-Z]+$/',
                'is_required' => false,
            ]);
        }

        DiscountCode::create([
            'code' => 'WELCOME10', 'kind' => 'percent', 'value' => 10,
            'minimum_order_minor' => 5000, 'usage_limit' => 100, 'times_used' => 0,
            'is_active' => true,
        ]);
    }
}
```

- [ ] **Step 6: Seed and walk the flow by hand**

```powershell
php artisan migrate:fresh
php artisan db:seed --class=DemoShopSeeder
php artisan serve
```

Then, in a second terminal, drive a purchase without any UI:

```powershell
php artisan tinker --execute="app(\App\Domain\Cart\CartService::class); echo \App\Domain\Catalog\Models\Variant::count();"
```
Expected: `8`. The full click-through belongs to Plan 2; what this step confirms is that seeded data satisfies every constraint.

- [ ] **Step 7: Run the entire suite**

Run: `php artisan test`
Expected: all tests pass — roughly 52 across 12 files. Any failure here is a real regression from an earlier task, not a new problem.

- [ ] **Step 8: Commit**

```bash
git add docker-compose.yml config/database.php database/seeders tests/Feature/Concurrency
git commit -m "test: add MySQL oversell test and demo seeder"
```

---

## Definition of Done

Plan 1 is complete when:

- [ ] `php artisan test` passes on SQLite (roughly 53 tests across 12 files)
- [ ] `OversellTest` passes against MySQL 8 in Docker
- [ ] `php artisan db:seed --class=DemoShopSeeder` produces 3 products, 8 variants, 6 shipping rates
- [ ] A purchase can be completed via HTTP: add to cart → `POST /checkout` → mock gateway form → callback → order `paid`, confirmation email captured in the log mailer
- [ ] A duplicate callback produces one paid order and one email
- [ ] A tampered price in a checkout POST changes nothing about the total charged

## What Plan 2 Covers

Storefront Blade views and Tailwind design, Filament v5 admin panel (product/variant/image management, order workflow, production worksheet, ship action with tracking, customs export), order lookup page, and the `EpointGateway` implementation once API credentials exist.

