# Filament Admin (Plan 2A) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the shop operator a working admin panel — an order lifecycle in the domain, six Filament resources, and a making-queue dashboard — so orders can actually be produced and shipped.

**Architecture:** Plan 1 built the buy side and stops at `paid`. Task 1 adds `OrderService::transition()`, the only code path allowed to change an order's status, with an `order_events` audit trail. Tasks 2–6 build a Filament v5 panel at `/admin` over the top: catalogue resources write Eloquent directly (it is CRUD), while every order mutation calls `transition()`. The panel is a presentation layer, never a second implementation of the domain.

**Tech Stack:** Laravel 12, PHP 8.5.4, Filament v5.7.6, Livewire 3, Pest 3, SQLite in-memory for tests, MySQL 8 in production.

## Global Constraints

Copied from `docs/superpowers/specs/2026-08-09-filament-admin-design.md`. Every task's requirements implicitly include this section.

- **Money is always an integer in minor units (qəpik).** Never float, never decimal. Variables and columns end in `_minor` or `Minor`. Conversion between manats and qəpik is string-parsed, never `(int) ($value * 100)`.
- **Currency is AZN only.** No multi-currency logic.
- **No admin code path writes `orders.status` directly.** Every status change goes through `OrderService::transition()`.
- **No admin action may set an order to `paid` or to `pending_payment`.** Only `markPaid()` (payment callback) creates `paid`; only `ReleaseExpiredReservations` retires an unpaid order.
- **`order_items` are snapshots.** Never joined live to the catalogue for display.
- **Stock (`variants.stock_quantity`) is a capacity cap the operator sets by hand**, not shelf inventory. It is labelled "Capacity" in the UI.
- **Guest checkout only.** No customer accounts. The `users` table holds operators only.
- **One operator.** No roles, no permissions package.
- **TDD for domain code (Task 1).** Write the failing test, watch it fail, implement, watch it pass.
- Composer is invoked as `php C:/php/composer.phar` (there is also a `composer.bat` shim on PATH).
- Tests run with `php artisan test`. The existing suite is 90 passing, 1 skipped — it must stay green.

---

## File Structure

**Task 1 — Order lifecycle (domain)**
- Create: `app/Domain/Order/IllegalTransitionException.php` — thrown on a move not in the transition table
- Create: `app/Domain/Order/Models/OrderEvent.php` — append-only audit row
- Create: `app/Mail/ShipmentNotification.php` + `resources/views/mail/shipment-notification.blade.php`
- Create: `database/migrations/2026_08_09_000100_add_ready_at_and_order_events.php`
- Modify: `app/Domain/Order/OrderService.php` — add `transition()` and its private helpers
- Modify: `app/Domain/Order/OrderStatus.php` — add `canTransitionTo()`
- Modify: `app/Domain/Order/Models/Order.php` — add `events()` relation, cast `ready_at`
- Create: `database/factories/OrderFactory.php`, `database/factories/OrderItemFactory.php`
- Test: `tests/Feature/Order/TransitionTest.php`

**Task 2 — Panel installation and access**
- Create: `app/Providers/Filament/AdminPanelProvider.php`
- Create: `app/Console/Commands/MakeAdminCommand.php`
- Modify: `app/Models/User.php` — implement `FilamentUser`
- Modify: `bootstrap/providers.php`, `composer.json`, `package.json`
- Test: `tests/Feature/Admin/PanelAccessTest.php`

**Task 3 — Product resource and its relation managers**
- Create: `app/Filament/Resources/Products/ProductResource.php` (+ `Pages/`, `RelationManagers/`)
- Create: `app/Support/MoneyInput.php` — the manat↔qəpik conversion used by every price field
- Test: `tests/Feature/Admin/ProductResourceTest.php`, `tests/Unit/Support/MoneyInputTest.php`

**Task 4 — Order, shipping, discount, payment-log resources**
- Create: `app/Filament/Resources/Orders/OrderResource.php` (+ pages, actions)
- Create: `app/Filament/Resources/ShippingZones/ShippingZoneResource.php` (+ rates relation manager)
- Create: `app/Filament/Resources/DiscountCodes/DiscountCodeResource.php`
- Create: `app/Filament/Resources/PaymentLogs/PaymentLogResource.php`
- Test: `tests/Feature/Admin/OrderResourceTest.php`, `tests/Feature/Admin/SupportingResourcesTest.php`

**Task 5 — Workshop dashboard**
- Create: `app/Filament/Pages/Workshop.php` + `resources/views/filament/pages/workshop.blade.php`
- Test: `tests/Feature/Admin/WorkshopTest.php`

**Task 6 — Deployment**
- Modify: `.gitignore` — un-ignore `/public/build`
- Modify: `routes/web.php` or `AdminPanelProvider` — login rate limiting
- Create: `deploy.md`
- Test: `tests/Feature/Admin/LoginThrottleTest.php`

---

## Task 1: Order lifecycle in the domain

No Filament in this task. This is money and stock, and it is TDD'd the way Plan 1's `markPaid()` was.

**Files:**
- Create: `app/Domain/Order/IllegalTransitionException.php`
- Create: `app/Domain/Order/Models/OrderEvent.php`
- Create: `app/Mail/ShipmentNotification.php`
- Create: `resources/views/mail/shipment-notification.blade.php`
- Create: `database/migrations/2026_08_09_000100_add_ready_at_and_order_events.php`
- Create: `database/factories/OrderFactory.php`
- Create: `database/factories/OrderItemFactory.php`
- Modify: `app/Domain/Order/OrderStatus.php`
- Modify: `app/Domain/Order/Models/Order.php`
- Modify: `app/Domain/Order/OrderService.php`
- Test: `tests/Feature/Order/TransitionTest.php`

**Interfaces:**
- Consumes: `OrderService::markPaid()` and `createFromCart()` (existing, unchanged); `PaymentGateway::refund(Order $order, int $amountMinor): RefundResult`; `RefundResult` has public `bool $succeeded` and `string $reference`.
- Produces:
  - `OrderStatus::canTransitionTo(OrderStatus $to): bool`
  - `OrderService::transition(Order $order, OrderStatus $to, ?string $note = null, ?int $userId = null, bool $restoreCapacity = false, ?string $trackingNumber = null): void`
  - `OrderService::markReady(Order $order): void`
  - `IllegalTransitionException extends \DomainException`
  - `OrderEvent` model with `order_id`, `from_status`, `to_status`, `note`, `user_id`, `created_at`
  - `Order::events()` HasMany, `Order::$casts['ready_at'] = 'datetime'`
  - `App\Mail\ShipmentNotification` (constructor: `Order $order`)

**Important context on existing schema:** `orders` **already has** `tracking_number` and `shipped_at` columns and `Order` already casts `shipped_at`. Do not re-add them. Only `ready_at` and the `order_events` table are new.

- [ ] **Step 1: Write the failing test file**

Create `tests/Feature/Order/TransitionTest.php`:

```php
<?php // tests/Feature/Order/TransitionTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Order\IllegalTransitionException;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderEvent;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Order\OrderService;
use App\Domain\Order\OrderStatus;
use App\Domain\Payment\PaymentGateway;
use App\Domain\Payment\Models\PaymentLog;
use App\Domain\Payment\RefundResult;
use App\Mail\ShipmentNotification;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->orders = app(OrderService::class);

    $this->product = Product::factory()->create();
    $this->variant = Variant::factory()->for($this->product)->create(['stock_quantity' => 7]);

    $this->order = Order::factory()->create([
        'status' => OrderStatus::Paid,
        'paid_at' => now()->subDay(),
    ]);

    OrderItem::factory()->for($this->order)->create([
        'variant_id' => $this->variant->id,
        'quantity' => 2,
    ]);
});

it('moves a paid order into production', function () {
    $this->orders->transition($this->order, OrderStatus::InProduction);

    expect($this->order->fresh()->status)->toBe(OrderStatus::InProduction);
});

it('records an order event with the from and to statuses and the acting user', function () {
    $this->orders->transition($this->order, OrderStatus::InProduction, 'Cutting today', userId: 42);

    $event = OrderEvent::where('order_id', $this->order->id)->sole();

    expect($event->from_status)->toBe('paid')
        ->and($event->to_status)->toBe('in_production')
        ->and($event->note)->toBe('Cutting today')
        ->and($event->user_id)->toBe(42);
});

it('rejects an illegal transition without writing the row', function () {
    expect(fn () => $this->orders->transition($this->order, OrderStatus::Delivered))
        ->toThrow(IllegalTransitionException::class);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(OrderEvent::count())->toBe(0);
});

it('refuses to set an order to paid', function () {
    $order = Order::factory()->create(['status' => OrderStatus::InProduction]);

    expect(fn () => $this->orders->transition($order, OrderStatus::Paid))
        ->toThrow(IllegalTransitionException::class);
});

it('refuses to set an order back to pending payment', function () {
    expect(fn () => $this->orders->transition($this->order, OrderStatus::PendingPayment))
        ->toThrow(IllegalTransitionException::class);
});

it('restores capacity when an order is cancelled', function () {
    $this->orders->transition($this->order, OrderStatus::Cancelled, 'Customer changed their mind');

    expect($this->variant->fresh()->stock_quantity)->toBe(9);
});

it('does not restore capacity on a refund by default', function () {
    $this->orders->transition($this->order, OrderStatus::Refunded);

    expect($this->variant->fresh()->stock_quantity)->toBe(7);
});

it('restores capacity on a refund when asked to', function () {
    $this->orders->transition($this->order, OrderStatus::Refunded, restoreCapacity: true);

    expect($this->variant->fresh()->stock_quantity)->toBe(9);
});

it('records a payment log when refunding', function () {
    $this->orders->transition($this->order, OrderStatus::Refunded);

    // MockGateway::refund() writes its own 'request' row, so filter by direction
    // rather than expecting a single log for this order.
    $log = PaymentLog::where('order_id', $this->order->id)->where('direction', 'refund')->sole();

    expect($log->payload['succeeded'])->toBeTrue()
        ->and($log->payload['amount_minor'])->toBe($this->order->total_minor);
});

it('leaves the order unchanged when the gateway refuses the refund', function () {
    $this->mock(PaymentGateway::class, function ($mock) {
        $mock->shouldReceive('refund')->once()
            ->andReturn(new RefundResult(succeeded: false, reference: 'DECLINED-1'));
    });

    expect(fn () => app(OrderService::class)->transition($this->order, OrderStatus::Refunded))
        ->toThrow(RuntimeException::class);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(OrderEvent::count())->toBe(0);
});

it('records the tracking number and shipped time and emails the customer', function () {
    $this->orders->transition($this->order, OrderStatus::InProduction);
    $this->orders->transition($this->order->fresh(), OrderStatus::Shipped, trackingNumber: 'AZ123456789AZ');

    $fresh = $this->order->fresh();

    expect($fresh->status)->toBe(OrderStatus::Shipped)
        ->and($fresh->tracking_number)->toBe('AZ123456789AZ')
        ->and($fresh->shipped_at)->not->toBeNull();

    Mail::assertQueued(ShipmentNotification::class,
        fn ($m) => $m->hasTo($this->order->customer_email));
});

it('refuses to ship without a tracking number', function () {
    $this->orders->transition($this->order, OrderStatus::InProduction);

    expect(fn () => $this->orders->transition($this->order->fresh(), OrderStatus::Shipped))
        ->toThrow(InvalidArgumentException::class);

    expect($this->order->fresh()->status)->toBe(OrderStatus::InProduction);
});

it('marks an order ready without changing its status', function () {
    $this->orders->transition($this->order, OrderStatus::InProduction);
    $this->orders->markReady($this->order->fresh());

    $fresh = $this->order->fresh();

    expect($fresh->ready_at)->not->toBeNull()
        ->and($fresh->status)->toBe(OrderStatus::InProduction);
});

it('does not restore capacity twice if cancel is somehow called again', function () {
    $this->orders->transition($this->order, OrderStatus::Cancelled);

    expect(fn () => $this->orders->transition($this->order->fresh(), OrderStatus::Cancelled))
        ->toThrow(IllegalTransitionException::class);

    expect($this->variant->fresh()->stock_quantity)->toBe(9);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=TransitionTest`
Expected: FAIL — `Class "App\Domain\Order\IllegalTransitionException" not found`, and the factories do not exist yet.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_08_09_000100_add_ready_at_and_order_events.php`:

```php
<?php // database/migrations/2026_08_09_000100_add_ready_at_and_order_events.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // tracking_number and shipped_at already exist from create_order_tables.
        Schema::table('orders', function (Blueprint $t) {
            $t->timestamp('ready_at')->nullable()->after('shipped_at');
        });

        Schema::create('order_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('from_status');
            $t->string('to_status');
            $t->text('note')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');

        Schema::table('orders', function (Blueprint $t) {
            $t->dropColumn('ready_at');
        });
    }
};
```

- [ ] **Step 4: Write the exception, the model, and the mailable**

Create `app/Domain/Order/IllegalTransitionException.php`:

```php
<?php // app/Domain/Order/IllegalTransitionException.php

namespace App\Domain\Order;

use DomainException;

class IllegalTransitionException extends DomainException
{
    public static function between(OrderStatus $from, OrderStatus $to): self
    {
        return new self("An order cannot move from {$from->label()} to {$to->label()}.");
    }
}
```

Create `app/Domain/Order/Models/OrderEvent.php`:

```php
<?php // app/Domain/Order/Models/OrderEvent.php

namespace App\Domain\Order\Models;

use Illuminate\Database\Eloquent\Model;

class OrderEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime'];

    public function order() { return $this->belongsTo(Order::class); }
}
```

Create `app/Mail/ShipmentNotification.php`:

```php
<?php // app/Mail/ShipmentNotification.php

namespace App\Mail;

use App\Domain\Order\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShipmentNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Order {$this->order->order_number} is on its way");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.shipment-notification',
            with: ['order' => $this->order],
        );
    }
}
```

Create `resources/views/mail/shipment-notification.blade.php`:

```blade
<x-mail::message>
# Your order is on its way

Hello {{ $order->customer_name }},

Order **{{ $order->order_number }}** has been posted.

Tracking number: **{{ $order->tracking_number }}**

Thank you for buying something made by hand.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
```

- [ ] **Step 5: Add the transition table to OrderStatus**

Modify `app/Domain/Order/OrderStatus.php` — add this method to the enum:

```php
    /**
     * The transitions an operator is allowed to make. PendingPayment and Paid
     * are absent from every target list: only the payment callback may create
     * Paid, and only ReleaseExpiredReservations may retire an unpaid order.
     */
    public function canTransitionTo(self $to): bool
    {
        return in_array($to, match ($this) {
            self::Paid => [self::InProduction, self::Cancelled, self::Refunded],
            self::InProduction => [self::Shipped, self::Cancelled, self::Refunded],
            self::Shipped => [self::Delivered, self::Refunded],
            self::Delivered => [self::Refunded],
            self::PendingPayment, self::Cancelled, self::Refunded => [],
        }, true);
    }
```

- [ ] **Step 6: Add the relation and cast to Order**

Modify `app/Domain/Order/Models/Order.php` — add `'ready_at' => 'datetime',` to `$casts` and add:

```php
    public function events() { return $this->hasMany(OrderEvent::class)->orderBy('created_at'); }
```

- [ ] **Step 7: Write the factories**

Create `database/factories/OrderFactory.php`:

```php
<?php // database/factories/OrderFactory.php

namespace Database\Factories;

use App\Domain\Order\Models\Order;
use App\Domain\Order\OrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'order_number' => 'LS-2026-' . Str::upper(Str::random(6)),
            'status' => OrderStatus::PendingPayment,
            'source' => 'web',
            'customer_email' => fake()->safeEmail(),
            'customer_name' => fake()->name(),
            'address_line1' => '1 Nizami St',
            'city' => 'Baku',
            'country_code' => 'AZ',
            'subtotal_minor' => 8900,
            'shipping_minor' => 500,
            'discount_minor' => 0,
            'total_minor' => 9400,
            'currency' => 'AZN',
            'total_weight_grams' => 120,
        ];
    }
}
```

Create `database/factories/OrderItemFactory.php`:

```php
<?php // database/factories/OrderItemFactory.php

namespace Database\Factories;

use App\Domain\Order\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'product_name' => 'Bifold wallet',
            'variant_description' => 'Cognac / natural thread',
            'sku' => strtoupper(fake()->unique()->bothify('WAL-####-??')),
            'unit_price_minor' => 4450,
            'quantity' => 1,
            'line_total_minor' => 4450,
            'personalization' => null,
            'weight_grams' => 120,
        ];
    }
}
```

Add `use HasFactory;` and the `newFactory()` hook to `Order` and `OrderItem`, matching the pattern already in `Product`:

```php
    protected static function newFactory()
    {
        return \Database\Factories\OrderFactory::new();
    }
```

(and `OrderItemFactory::new()` in `OrderItem`).

- [ ] **Step 8: Implement `transition()` and `markReady()`**

Add to `app/Domain/Order/OrderService.php`. Add `use App\Domain\Order\Models\OrderEvent;`, `use App\Domain\Payment\PaymentGateway;`, `use App\Domain\Payment\Models\PaymentLog;`, `use App\Mail\ShipmentNotification;`, `use InvalidArgumentException;` and `use RuntimeException;` to the imports.

```php
    /**
     * The single guarded entry point for every operator-driven status change.
     * Nothing else in the application may write orders.status.
     */
    public function transition(
        Order $order,
        OrderStatus $to,
        ?string $note = null,
        ?int $userId = null,
        bool $restoreCapacity = false,
        ?string $trackingNumber = null,
    ): void {
        if ($to === OrderStatus::Shipped && blank($trackingNumber)) {
            throw new InvalidArgumentException('A tracking number is required to mark an order shipped.');
        }

        // The gateway call happens outside the transaction: a refund that
        // succeeds at the bank but fails locally is recoverable, whereas a
        // transaction held open across a network call is not.
        $refund = null;

        if ($to === OrderStatus::Refunded) {
            $refund = app(PaymentGateway::class)->refund($order, $order->total_minor);

            PaymentLog::create([
                'order_id' => $order->id,
                'gateway' => class_basename(app(PaymentGateway::class)),
                'direction' => 'refund',
                'reference' => $refund->reference,
                'payload' => ['succeeded' => $refund->succeeded, 'amount_minor' => $order->total_minor],
            ]);

            if (! $refund->succeeded) {
                throw new RuntimeException(
                    "The payment provider refused the refund (reference {$refund->reference}). The order is unchanged."
                );
            }
        }

        DB::transaction(function () use ($order, $to, $note, $userId, $restoreCapacity, $trackingNumber) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->status->canTransitionTo($to)) {
                throw IllegalTransitionException::between($locked?->status ?? $order->status, $to);
            }

            $from = $locked->status;

            $attributes = ['status' => $to];

            if ($to === OrderStatus::Shipped) {
                $attributes['tracking_number'] = $trackingNumber;
                $attributes['shipped_at'] = now();
            }

            $locked->update($attributes);

            if ($to === OrderStatus::Cancelled || ($to === OrderStatus::Refunded && $restoreCapacity)) {
                $this->restoreCapacity($locked);
            }

            OrderEvent::create([
                'order_id' => $locked->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'note' => $note,
                'user_id' => $userId,
                'created_at' => now(),
            ]);
        });

        if ($to === OrderStatus::Shipped) {
            Mail::to($order->customer_email)->queue(new ShipmentNotification($order->fresh()));
        }
    }

    /**
     * "Made, not yet posted." A workshop bookkeeping mark, not a status change —
     * the domain transition to Shipped still happens once, at the post office.
     */
    public function markReady(Order $order): void
    {
        $order->update(['ready_at' => now()]);
    }

    private function restoreCapacity(Order $order): void
    {
        foreach ($order->items as $item) {
            if (! $item->variant_id) {
                continue;
            }

            $variant = Variant::whereKey($item->variant_id)->lockForUpdate()->first();

            $variant?->increment('stock_quantity', $item->quantity);
        }
    }
```

- [ ] **Step 9: Run the test to verify it passes**

Run: `php artisan test --filter=TransitionTest`
Expected: PASS, all 14 tests, output pristine.

- [ ] **Step 10: Run the full suite**

Run: `php artisan test`
Expected: 104 passing, 1 skipped. If anything from Plan 1 broke, stop and report — Task 1 must not disturb the existing suite.

- [ ] **Step 11: Commit**

```bash
git add app/Domain/Order app/Mail/ShipmentNotification.php resources/views/mail/shipment-notification.blade.php database/migrations database/factories tests/Feature/Order/TransitionTest.php
git commit -m "feat: add guarded order lifecycle transitions with audit trail"
```

---

## Task 2: Filament panel, access control, and the admin account

**Files:**
- Create: `app/Providers/Filament/AdminPanelProvider.php` (generated, then edited)
- Create: `app/Console/Commands/MakeAdminCommand.php`
- Modify: `app/Models/User.php`
- Modify: `bootstrap/providers.php` (Filament's installer does this)
- Test: `tests/Feature/Admin/PanelAccessTest.php`

**Interfaces:**
- Consumes: nothing from Task 1.
- Produces: a Filament panel with id `admin` at path `/admin`; `User implements FilamentUser`; artisan command `shop:make-admin`. Tasks 3–5 register resources and pages into this panel via its `discoverResources()` / `discoverPages()` calls.

- [ ] **Step 1: Install Filament**

```bash
php C:/php/composer.phar require filament/filament:"^5.7"
php artisan filament:install --panels
```

When the installer asks for the panel ID, answer `admin`. It creates `app/Providers/Filament/AdminPanelProvider.php` and registers it in `bootstrap/providers.php`.

Filament v5.7.6 requires PHP `^8.2` and `ext-intl` — both already satisfied (PHP 8.5.4, intl loaded).

- [ ] **Step 2: Write the failing access test**

Create `tests/Feature/Admin/PanelAccessTest.php`:

```php
<?php // tests/Feature/Admin/PanelAccessTest.php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('redirects an anonymous visitor to the login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('lets an operator in', function () {
    $this->actingAs(User::factory()->create(['is_operator' => true]))
        ->get('/admin')
        ->assertSuccessful();
});

it('refuses a user who is not an operator', function () {
    $this->actingAs(User::factory()->create(['is_operator' => false]))
        ->get('/admin')
        ->assertForbidden();
});

it('has no public registration route', function () {
    $this->post('/admin/register')->assertNotFound();
});

it('creates an operator from the console command', function () {
    $this->artisan('shop:make-admin', [
        '--name' => 'Matin',
        '--email' => 'owner@example.com',
        '--password' => 'correct-horse-battery',
    ])->assertSuccessful();

    $user = User::where('email', 'owner@example.com')->sole();

    expect($user->is_operator)->toBeTrue()
        ->and(Hash::check('correct-horse-battery', $user->password))->toBeTrue();
});

it('refuses to create a second operator with the same email', function () {
    User::factory()->create(['email' => 'owner@example.com']);

    $this->artisan('shop:make-admin', [
        '--name' => 'Matin',
        '--email' => 'owner@example.com',
        '--password' => 'correct-horse-battery',
    ])->assertFailed();
});
```

- [ ] **Step 3: Run it to verify it fails**

Run: `php artisan test --filter=PanelAccessTest`
Expected: FAIL — no `is_operator` column, no `shop:make-admin` command.

- [ ] **Step 4: Add the `is_operator` column**

Create `database/migrations/2026_08_09_000200_add_is_operator_to_users.php`:

```php
<?php // database/migrations/2026_08_09_000200_add_is_operator_to_users.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_operator')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('is_operator');
        });
    }
};
```

Add `'is_operator'` to `User::$fillable` and `'is_operator' => 'boolean'` to `User::casts()`.

- [ ] **Step 5: Implement `FilamentUser` on User**

Modify `app/Models/User.php`:

```php
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    // ... existing traits and properties

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_operator;
    }
}
```

Multi-factor auth is out of scope — do not add it.

- [ ] **Step 6: Write the console command**

Create `app/Console/Commands/MakeAdminCommand.php`:

```php
<?php // app/Console/Commands/MakeAdminCommand.php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeAdminCommand extends Command
{
    protected $signature = 'shop:make-admin
                            {--name= : The operator\'s name}
                            {--email= : The operator\'s email address}
                            {--password= : The operator\'s password}';

    protected $description = 'Create an operator account that can access the admin panel';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Password');

        if (User::where('email', $email)->exists()) {
            $this->error("A user with the email {$email} already exists.");

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_operator' => true,
        ]);

        $this->info("Operator {$email} created. Sign in at /admin.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 7: Confirm registration is disabled in the panel**

Open `app/Providers/Filament/AdminPanelProvider.php`. It must call `->login()` but **must not** call `->registration()` or `->passwordReset()`. Remove them if the installer added them. Set `->brandName('Leather Shop')`.

- [ ] **Step 8: Run the test to verify it passes**

Run: `php artisan test --filter=PanelAccessTest`
Expected: PASS, 6 tests.

- [ ] **Step 9: Run the full suite and commit**

Run: `php artisan test`
Expected: 110 passing, 1 skipped.

```bash
git add composer.json composer.lock package.json app bootstrap database/migrations tests/Feature/Admin resources
git commit -m "feat: add Filament admin panel with operator-only access"
```

---

## Task 3: Product resource, its relation managers, and money conversion

**Files:**
- Create: `app/Support/MoneyInput.php`
- Create: `app/Filament/Resources/Products/ProductResource.php` and its `Pages/` and `RelationManagers/` directories
- Test: `tests/Unit/Support/MoneyInputTest.php`, `tests/Feature/Admin/ProductResourceTest.php`

**Interfaces:**
- Consumes: `Product`, `Variant`, `ProductImage`, `VariantOption`, `PersonalizationOption` models from Plan 1; the panel from Task 2.
- Produces: `App\Support\MoneyInput::toMinor(?string $manats): ?int` and `MoneyInput::toManats(?int $minor): ?string`, used by Task 4's shipping and discount forms.

- [ ] **Step 1: Write the failing money conversion test**

Create `tests/Unit/Support/MoneyInputTest.php`:

```php
<?php // tests/Unit/Support/MoneyInputTest.php

use App\Support\MoneyInput;

it('converts manats to qepik without floating point', function () {
    expect(MoneyInput::toMinor('49.99'))->toBe(4999)
        ->and(MoneyInput::toMinor('0.01'))->toBe(1)
        ->and(MoneyInput::toMinor('100'))->toBe(10000)
        ->and(MoneyInput::toMinor('100.5'))->toBe(10050)
        ->and(MoneyInput::toMinor('1234.56'))->toBe(123456);
});

it('accepts a comma as the decimal separator', function () {
    expect(MoneyInput::toMinor('49,99'))->toBe(4999);
});

it('rounds a third decimal place rather than truncating it', function () {
    expect(MoneyInput::toMinor('1.005'))->toBe(101)
        ->and(MoneyInput::toMinor('1.004'))->toBe(100);
});

it('passes null through', function () {
    expect(MoneyInput::toMinor(null))->toBeNull()
        ->and(MoneyInput::toMinor(''))->toBeNull()
        ->and(MoneyInput::toManats(null))->toBeNull();
});

it('converts qepik back to a two-decimal string', function () {
    expect(MoneyInput::toManats(4999))->toBe('49.99')
        ->and(MoneyInput::toManats(10000))->toBe('100.00')
        ->and(MoneyInput::toManats(1))->toBe('0.01')
        ->and(MoneyInput::toManats(0))->toBe('0.00');
});

it('round-trips every value without drift', function () {
    foreach ([1, 99, 100, 4999, 10000, 123456, 999999] as $minor) {
        expect(MoneyInput::toMinor(MoneyInput::toManats($minor)))->toBe($minor);
    }
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=MoneyInputTest`
Expected: FAIL — `Class "App\Support\MoneyInput" not found`.

- [ ] **Step 3: Implement MoneyInput**

Create `app/Support/MoneyInput.php`:

```php
<?php // app/Support/MoneyInput.php

namespace App\Support;

/**
 * Converts between the manats-with-decimals the operator types and the integer
 * qepik the domain stores. Parsing is done on the string: (int) ($value * 100)
 * silently produces 4998 for "49.99" on some inputs, and a shop that charges a
 * qepik less than it meant to is a shop with a bug nobody notices for a year.
 */
final class MoneyInput
{
    public static function toMinor(?string $manats): ?int
    {
        if ($manats === null || trim($manats) === '') {
            return null;
        }

        $normalized = str_replace([',', ' '], ['.', ''], trim($manats));

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');

        $fraction = str_pad(substr($fraction, 0, 3), 3, '0');

        $thousandths = ((int) $whole) * 1000 + ((int) substr($fraction, 0, 3));

        return intdiv($thousandths, 10) + (($thousandths % 10) >= 5 ? 1 : 0);
    }

    public static function toManats(?int $minor): ?string
    {
        if ($minor === null) {
            return null;
        }

        return number_format($minor / 100, 2, '.', '');
    }
}
```

- [ ] **Step 4: Run it to verify it passes**

Run: `php artisan test --filter=MoneyInputTest`
Expected: PASS, 6 tests.

- [ ] **Step 5: Generate the product resource**

```bash
php artisan make:filament-resource Product --model-namespace="App\Domain\Catalog\Models" --generate
```

If the generator cannot resolve the namespaced model, create the resource by hand at `app/Filament/Resources/Products/ProductResource.php` following Filament v5's structure and report the deviation.

- [ ] **Step 6: Write the failing resource test**

Create `tests/Feature/Admin/ProductResourceTest.php`:

```php
<?php // tests/Feature/Admin/ProductResourceTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_operator' => true]));
});

it('lists products', function () {
    $products = Product::factory()->count(3)->create();

    livewire(ListProducts::class)
        ->assertCanSeeTableRecords($products);
});

it('stores the price the operator typed as qepik', function () {
    livewire(CreateProduct::class)
        ->fillForm([
            'name' => 'Card holder',
            'slug' => 'card-holder',
            'base_price_minor' => '49.99',
            'lead_time_days' => 3,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Product::where('slug', 'card-holder')->sole()->base_price_minor)->toBe(4999);
});

it('does not drift the price when the record is saved without touching it', function () {
    $product = Product::factory()->create(['base_price_minor' => 4999]);

    livewire(EditProduct::class, ['record' => $product->getKey()])
        ->fillForm(['name' => 'Renamed wallet'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->base_price_minor)->toBe(4999);
});

it('rejects a product with no name', function () {
    livewire(CreateProduct::class)
        ->fillForm(['name' => '', 'slug' => 'x', 'base_price_minor' => '10.00'])
        ->call('create')
        ->assertHasFormErrors(['name']);
});

it('rejects a duplicate slug', function () {
    Product::factory()->create(['slug' => 'bifold']);

    livewire(CreateProduct::class)
        ->fillForm(['name' => 'Another', 'slug' => 'bifold', 'base_price_minor' => '10.00'])
        ->call('create')
        ->assertHasFormErrors(['slug']);
});

it('locks the slug once the product has been ordered', function () {
    $product = Product::factory()->create(['slug' => 'sold-wallet']);
    $variant = Variant::factory()->for($product)->create();
    OrderItem::factory()->for(Order::factory()->create())->create(['variant_id' => $variant->id]);

    livewire(EditProduct::class, ['record' => $product->getKey()])
        ->assertFormFieldIsDisabled('slug');
});

it('leaves the slug editable on a product nobody has ordered', function () {
    $product = Product::factory()->create();

    livewire(EditProduct::class, ['record' => $product->getKey()])
        ->assertFormFieldIsEnabled('slug');
});
```

- [ ] **Step 7: Run it to verify it fails**

Run: `php artisan test --filter=ProductResourceTest`
Expected: FAIL — the generated form has no money conversion and no slug lock.

- [ ] **Step 8: Write the product form and table**

`app/Filament/Resources/Products/ProductResource.php` — the form schema:

```php
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

                    TextInput::make('base_price_minor')
                        ->label('Price')
                        ->prefix('AZN')
                        ->required()
                        ->rule('regex:/^\d+([.,]\d{1,2})?$/')
                        ->formatStateUsing(fn (?int $state) => MoneyInput::toManats($state))
                        ->dehydrateStateUsing(fn (?string $state) => MoneyInput::toMinor($state)),

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
                        ->reorderable(),
                ]),

                Tabs\Tab::make('Options')->schema([
                    Repeater::make('variantOptions')
                        ->relationship()
                        ->schema([
                            TextInput::make('name')->required()->placeholder('Leather colour'),
                            Repeater::make('values')
                                ->relationship()
                                ->schema([TextInput::make('value')->required()])
                                ->orderColumn('sort_order'),
                        ])
                        ->orderColumn('sort_order'),

                    Repeater::make('personalizationOptions')
                        ->relationship()
                        ->schema([
                            Select::make('type')->options([
                                'monogram' => 'Monogram',
                                'gift_wrap' => 'Gift wrap',
                                'custom_stamp' => 'Custom stamp',
                            ])->required(),
                            TextInput::make('label')->required(),
                            TextInput::make('price_delta_minor')
                                ->label('Extra charge')
                                ->prefix('AZN')
                                ->formatStateUsing(fn (?int $state) => MoneyInput::toManats($state ?? 0))
                                ->dehydrateStateUsing(fn (?string $state) => MoneyInput::toMinor($state) ?? 0),
                            TextInput::make('max_characters')->numeric()->default(3),
                            TextInput::make('allowed_pattern')->default('/^[A-Z]+$/'),
                            Toggle::make('is_required'),
                        ]),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    /** A product is locked once any order item points at one of its variants. */
    private static function hasBeenOrdered(Product $product): bool
    {
        return OrderItem::whereIn('variant_id', $product->variants()->pluck('id'))->exists();
    }
```

The table:

```php
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
```

`VariantOption` needs a `values()` relation for the nested repeater — add it to `app/Domain/Catalog/Models/VariantOption.php` if it is not already there:

```php
    public function values() { return $this->hasMany(OptionValue::class)->orderBy('sort_order'); }
```

- [ ] **Step 9: Add the Variants relation manager**

Create `app/Filament/Resources/Products/RelationManagers/VariantsRelationManager.php`:

```php
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
```

The form for creating and editing a variant:

```php
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('sku')->required()->unique(ignoreRecord: true),
            TextInput::make('description')->placeholder('Cognac / natural thread'),
            TextInput::make('price_minor_override')
                ->label('Price override')
                ->prefix('AZN')
                ->helperText('Leave blank to use the product price.')
                ->rule('regex:/^\d+([.,]\d{1,2})?$/')
                ->formatStateUsing(fn (?int $state) => MoneyInput::toManats($state))
                ->dehydrateStateUsing(fn (?string $state) => MoneyInput::toMinor($state)),
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
```

Register it in `ProductResource::getRelations()`. Variants must **not** appear as a top-level navigation item.

- [ ] **Step 10: Run the tests to verify they pass**

Run: `php artisan test --filter="ProductResourceTest|MoneyInputTest"`
Expected: PASS, 13 tests.

- [ ] **Step 11: Run the full suite and commit**

Run: `php artisan test`

```bash
git add app/Support app/Filament app/Domain/Catalog tests/Unit/Support tests/Feature/Admin
git commit -m "feat: add product resource with variant management and safe money input"
```

---

## Task 4: Order, shipping, discount, and payment-log resources

**Files:**
- Create: `app/Filament/Resources/Orders/OrderResource.php` + `Pages/ListOrders.php`, `Pages/ViewOrder.php`
- Create: `app/Filament/Resources/ShippingZones/ShippingZoneResource.php` + `RelationManagers/RatesRelationManager.php`
- Create: `app/Filament/Resources/DiscountCodes/DiscountCodeResource.php`
- Create: `app/Filament/Resources/PaymentLogs/PaymentLogResource.php`
- Test: `tests/Feature/Admin/OrderResourceTest.php`, `tests/Feature/Admin/SupportingResourcesTest.php`

**Interfaces:**
- Consumes: `OrderService::transition()` and `markReady()` from Task 1; `MoneyInput` from Task 3.
- Produces: a reusable `App\Filament\Actions\TransitionActions::for(OrderStatus $to)` factory returning a configured Filament `Action`, used again by Task 5's Workshop page.

- [ ] **Step 1: Write the failing order resource test**

Create `tests/Feature/Admin/OrderResourceTest.php`:

```php
<?php // tests/Feature/Admin/OrderResourceTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderEvent;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Order\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Mail::fake();
    $this->user = User::factory()->create(['is_operator' => true]);
    $this->actingAs($this->user);

    $this->product = Product::factory()->create();
    $this->variant = Variant::factory()->for($this->product)->create(['stock_quantity' => 5]);

    $this->order = Order::factory()->create([
        'status' => OrderStatus::Paid,
        'paid_at' => now()->subDays(2),
    ]);

    OrderItem::factory()->for($this->order)->create([
        'variant_id' => $this->variant->id,
        'quantity' => 1,
        'personalization' => ['monogram' => 'MA'],
    ]);
});

it('lists orders', function () {
    livewire(ListOrders::class)->assertCanSeeTableRecords([$this->order]);
});

it('has no create action', function () {
    expect(App\Filament\Resources\Orders\OrderResource::canCreate())->toBeFalse();
});

it('shows the snapshot line items and the personalization', function () {
    livewire(ViewOrder::class, ['record' => $this->order->getKey()])
        ->assertSee('MA')
        ->assertSee($this->order->order_number);
});

it('shows the snapshot name even after the product is renamed', function () {
    $this->product->update(['name' => 'Completely different name']);

    livewire(ViewOrder::class, ['record' => $this->order->getKey()])
        ->assertSee('Bifold wallet')
        ->assertDontSee('Completely different name');
});

it('moves an order into production through the action', function () {
    livewire(ViewOrder::class, ['record' => $this->order->getKey()])
        ->callAction('start_production');

    expect($this->order->fresh()->status)->toBe(OrderStatus::InProduction);
});

it('records the acting user on the event', function () {
    livewire(ViewOrder::class, ['record' => $this->order->getKey()])
        ->callAction('start_production');

    expect(OrderEvent::sole()->user_id)->toBe($this->user->id);
});

it('requires a tracking number to ship', function () {
    $this->order->update(['status' => OrderStatus::InProduction]);

    livewire(ViewOrder::class, ['record' => $this->order->getKey()])
        ->callAction('ship', data: ['tracking_number' => ''])
        ->assertHasActionErrors(['tracking_number']);

    expect($this->order->fresh()->status)->toBe(OrderStatus::InProduction);
});

it('ships with a tracking number', function () {
    $this->order->update(['status' => OrderStatus::InProduction]);

    livewire(ViewOrder::class, ['record' => $this->order->getKey()])
        ->callAction('ship', data: ['tracking_number' => 'AZ123456789AZ']);

    expect($this->order->fresh()->tracking_number)->toBe('AZ123456789AZ');
});

it('restores capacity when cancelled from the panel', function () {
    livewire(ViewOrder::class, ['record' => $this->order->getKey()])
        ->callAction('cancel', data: ['note' => 'Customer changed their mind']);

    expect($this->variant->fresh()->stock_quantity)->toBe(6);
});

it('hides the production action on an order that is already shipped', function () {
    $this->order->update(['status' => OrderStatus::Shipped]);

    livewire(ViewOrder::class, ['record' => $this->order->getKey()])
        ->assertActionHidden('start_production');
});

it('shows the event history', function () {
    app(App\Domain\Order\OrderService::class)
        ->transition($this->order, OrderStatus::InProduction, 'Cutting today', $this->user->id);

    livewire(ViewOrder::class, ['record' => $this->order->fresh()->getKey()])
        ->assertSee('Cutting today');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=OrderResourceTest`
Expected: FAIL — the resource does not exist.

- [ ] **Step 3: Write the transition action factory**

Create `app/Filament/Actions/TransitionActions.php`:

```php
<?php // app/Filament/Actions/TransitionActions.php

namespace App\Filament\Actions;

use App\Domain\Order\IllegalTransitionException;
use App\Domain\Order\Models\Order;
use App\Domain\Order\OrderService;
use App\Domain\Order\OrderStatus;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use RuntimeException;

/**
 * Every admin status change in the panel is built here, so there is exactly one
 * place that calls OrderService::transition(). Nothing in Filament writes
 * orders.status.
 */
class TransitionActions
{
    public static function startProduction(): Action
    {
        return Action::make('start_production')
            ->label('Start making')
            ->icon('heroicon-o-play')
            ->visible(fn (Order $record) => $record->status->canTransitionTo(OrderStatus::InProduction))
            ->action(fn (Order $record) => static::run($record, OrderStatus::InProduction));
    }

    public static function markReady(): Action
    {
        return Action::make('mark_ready')
            ->label('Mark made')
            ->icon('heroicon-o-check')
            ->visible(fn (Order $record) => $record->status === OrderStatus::InProduction && $record->ready_at === null)
            ->action(function (Order $record) {
                app(OrderService::class)->markReady($record);

                Notification::make()->success()->title('Marked as made.')->send();
            });
    }

    public static function ship(): Action
    {
        return Action::make('ship')
            ->label('Mark posted')
            ->icon('heroicon-o-truck')
            ->visible(fn (Order $record) => $record->status->canTransitionTo(OrderStatus::Shipped))
            ->schema([
                TextInput::make('tracking_number')->required()->label('Tracking number'),
            ])
            ->action(fn (Order $record, array $data) => static::run(
                $record,
                OrderStatus::Shipped,
                trackingNumber: $data['tracking_number'],
            ));
    }

    public static function deliver(): Action
    {
        return Action::make('deliver')
            ->label('Mark delivered')
            ->visible(fn (Order $record) => $record->status->canTransitionTo(OrderStatus::Delivered))
            ->action(fn (Order $record) => static::run($record, OrderStatus::Delivered));
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Cancel order')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Cancelling returns this order\'s capacity to the variants.')
            ->visible(fn (Order $record) => $record->status->canTransitionTo(OrderStatus::Cancelled))
            ->schema([Textarea::make('note')->label('Why?')->required()])
            ->action(fn (Order $record, array $data) => static::run($record, OrderStatus::Cancelled, $data['note']));
    }

    public static function refund(): Action
    {
        return Action::make('refund')
            ->label('Refund')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Order $record) => $record->status->canTransitionTo(OrderStatus::Refunded))
            ->schema([
                Textarea::make('note')->label('Why?')->required(),
                Toggle::make('restore_capacity')
                    ->label('Return capacity to the variant')
                    ->helperText('Leave off if the piece was already made.')
                    ->default(false),
            ])
            ->action(fn (Order $record, array $data) => static::run(
                $record,
                OrderStatus::Refunded,
                $data['note'],
                restoreCapacity: $data['restore_capacity'],
            ));
    }

    private static function run(
        Order $order,
        OrderStatus $to,
        ?string $note = null,
        bool $restoreCapacity = false,
        ?string $trackingNumber = null,
    ): void {
        try {
            app(OrderService::class)->transition(
                order: $order,
                to: $to,
                note: $note,
                userId: auth()->id(),
                restoreCapacity: $restoreCapacity,
                trackingNumber: $trackingNumber,
            );
        } catch (IllegalTransitionException | RuntimeException $e) {
            // The gateway refusing a refund is the case that matters here: the
            // operator must see what actually happened, never an assumed success.
            Notification::make()->danger()->title('Could not update the order')->body($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title("Order is now {$to->label()}.")->send();
    }
}
```

- [ ] **Step 4: Write the order resource**

`app/Filament/Resources/Orders/OrderResource.php`:

```php
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
```

`Pages/ViewOrder.php` — an infolist showing the customer block, the snapshot items (read from `$record->items`, never re-joined to the catalogue), the payment logs, and the `order_events` history; plus the header actions:

```php
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
```

No `EditOrder` page and no editable fields anywhere on this resource.

- [ ] **Step 5: Run the order test to verify it passes**

Run: `php artisan test --filter=OrderResourceTest`
Expected: PASS, 11 tests.

- [ ] **Step 6: Write the failing supporting-resources test**

Create `tests/Feature/Admin/SupportingResourcesTest.php`:

```php
<?php // tests/Feature/Admin/SupportingResourcesTest.php

use App\Domain\Discount\Models\DiscountCode;
use App\Domain\Payment\Models\PaymentLog;
use App\Domain\Shipping\Models\ShippingZone;
use App\Filament\Resources\DiscountCodes\Pages\CreateDiscountCode;
use App\Filament\Resources\DiscountCodes\Pages\EditDiscountCode;
use App\Filament\Resources\DiscountCodes\Pages\ListDiscountCodes;
use App\Filament\Resources\PaymentLogs\PaymentLogResource;
use App\Filament\Resources\PaymentLogs\Pages\ListPaymentLogs;
use App\Filament\Resources\ShippingZones\Pages\CreateShippingZone;
use App\Filament\Resources\ShippingZones\Pages\ListShippingZones;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_operator' => true]));
});

it('lists shipping zones', function () {
    $zone = ShippingZone::create(['name' => 'Azerbaijan', 'country_codes' => ['AZ']]);

    livewire(ListShippingZones::class)->assertCanSeeTableRecords([$zone]);
});

it('creates a shipping zone', function () {
    livewire(CreateShippingZone::class)
        ->fillForm(['name' => 'Regional', 'country_codes' => ['GE', 'TR'], 'is_fallback' => false])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ShippingZone::where('name', 'Regional')->sole()->country_codes)->toBe(['GE', 'TR']);
});

it('creates a discount code', function () {
    livewire(CreateDiscountCode::class)
        ->fillForm([
            'code' => 'WELCOME10',
            'kind' => 'percent',
            'value' => 10,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(DiscountCode::where('code', 'WELCOME10')->exists())->toBeTrue();
});

it('rejects a duplicate discount code', function () {
    DiscountCode::create(['code' => 'WELCOME10', 'kind' => 'percent', 'value' => 10]);

    livewire(CreateDiscountCode::class)
        ->fillForm(['code' => 'WELCOME10', 'kind' => 'percent', 'value' => 10])
        ->call('create')
        ->assertHasFormErrors(['code']);
});

it('has no times_used field on the form', function () {
    $code = DiscountCode::create(['code' => 'EXISTING', 'kind' => 'percent', 'value' => 10, 'times_used' => 4]);

    livewire(EditDiscountCode::class, ['record' => $code->getKey()])
        ->assertFormFieldDoesNotExist('times_used');
});

it('ignores a times_used value pushed at the create form', function () {
    livewire(CreateDiscountCode::class)
        ->fillForm(['code' => 'SNEAKY', 'kind' => 'percent', 'value' => 10, 'times_used' => 99])
        ->call('create');

    // times_used belongs to DiscountService::consume()'s atomic increment.
    expect(DiscountCode::where('code', 'SNEAKY')->sole()->times_used)->toBe(0);
});

it('lists payment logs and refuses creation', function () {
    PaymentLog::create([
        'gateway' => 'MockGateway',
        'direction' => 'callback',
        'reference' => 'REF-1',
        'payload' => ['status' => 'paid'],
    ]);

    livewire(ListPaymentLogs::class)->assertSuccessful();

    expect(PaymentLogResource::canCreate())->toBeFalse()
        ->and(PaymentLogResource::canEdit(PaymentLog::first()))->toBeFalse();
});
```

- [ ] **Step 7: Run it to verify it fails**

Run: `php artisan test --filter=SupportingResourcesTest`
Expected: FAIL — the resources do not exist.

- [ ] **Step 8: Write the three supporting resources**

**ShippingZoneResource** form:

```php
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
                ->helperText('Orders to countries in no other zone are priced here.'),
        ]);
    }
```

`RelationManagers/RatesRelationManager.php` form and table:

```php
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->placeholder('Standard'),
            TextInput::make('min_weight_grams')->numeric()->minValue(0)->default(0)->required(),
            TextInput::make('max_weight_grams')->numeric()->minValue(1)->required(),
            TextInput::make('price_minor')
                ->label('Price')
                ->prefix('AZN')
                ->required()
                ->rule('regex:/^\d+([.,]\d{1,2})?$/')
                ->formatStateUsing(fn (?int $state) => MoneyInput::toManats($state))
                ->dehydrateStateUsing(fn (?string $state) => MoneyInput::toMinor($state)),
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
```

**DiscountCodeResource** form and table:

```php
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

            TextInput::make('minimum_order_minor')
                ->label('Minimum order')
                ->prefix('AZN')
                ->default(0)
                ->rule('regex:/^\d+([.,]\d{1,2})?$/')
                ->formatStateUsing(fn (?int $state) => MoneyInput::toManats($state ?? 0))
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
```

`times_used` must appear in **no** form schema on this resource.

**PaymentLogResource**:

```php
    public static function canCreate(): bool { return false; }

    public static function canEdit(Model $record): bool { return false; }

    public static function canDelete(Model $record): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('order.order_number')->label('Order')->searchable(),
                TextColumn::make('gateway'),
                TextColumn::make('direction')->badge(),
                TextColumn::make('reference')->searchable()->copyable(),
            ])
            ->filters([
                SelectFilter::make('direction')->options([
                    'request' => 'Request',
                    'callback' => 'Callback',
                    'refund' => 'Refund',
                ]),
            ])
            ->recordActions([
                ViewAction::make()->infolist([
                    KeyValueEntry::make('payload')->label('Raw gateway payload'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
```

- [ ] **Step 9: Run both tests to verify they pass**

Run: `php artisan test --filter="OrderResourceTest|SupportingResourcesTest"`
Expected: PASS, 17 tests.

- [ ] **Step 10: Run the full suite and commit**

Run: `php artisan test`

```bash
git add app/Filament tests/Feature/Admin
git commit -m "feat: add order, shipping, discount, and payment log resources"
```

---

## Task 5: The Workshop dashboard

**Files:**
- Create: `app/Filament/Pages/Workshop.php`
- Create: `resources/views/filament/pages/workshop.blade.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php` — make Workshop the home page
- Test: `tests/Feature/Admin/WorkshopTest.php`

**Interfaces:**
- Consumes: `TransitionActions` from Task 4; `OrderStatus`, `Order`, `OrderItem` from Task 1.
- Produces: nothing later tasks depend on.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/WorkshopTest.php`:

```php
<?php // tests/Feature/Admin/WorkshopTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Order\OrderStatus;
use App\Filament\Pages\Workshop;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Mail::fake();
    $this->actingAs(User::factory()->create(['is_operator' => true]));

    $variant = Variant::factory()->for(Product::factory())->create();

    $this->makeOrder = function (OrderStatus $status, array $extra = []) use ($variant) {
        $order = Order::factory()->create(array_merge([
            'status' => $status,
            'paid_at' => now()->subDay(),
        ], $extra));

        OrderItem::factory()->for($order)->create([
            'variant_id' => $variant->id,
            'personalization' => ['monogram' => 'MA'],
        ]);

        return $order;
    };
});

/** Reads a column off the mounted page as a plain array of ids. */
function columnIds(\Livewire\Features\SupportTesting\Testable $page, string $property): array
{
    return collect($page->get($property))->pluck('id')->all();
}

it('puts a paid order in the to-make column', function () {
    $order = ($this->makeOrder)(OrderStatus::Paid);

    $page = livewire(Workshop::class)->assertSee($order->order_number);

    expect(columnIds($page, 'toMake'))->toBe([$order->id])
        ->and(columnIds($page, 'inProduction'))->toBe([]);
});

it('puts an in-production order in the making column', function () {
    $order = ($this->makeOrder)(OrderStatus::InProduction);

    $page = livewire(Workshop::class);

    expect(columnIds($page, 'inProduction'))->toBe([$order->id])
        ->and(columnIds($page, 'toMake'))->toBe([]);
});

it('puts an order marked made in the ready-to-post column', function () {
    $order = ($this->makeOrder)(OrderStatus::InProduction, ['ready_at' => now()]);

    $page = livewire(Workshop::class);

    expect(columnIds($page, 'readyToPost'))->toBe([$order->id])
        ->and(columnIds($page, 'inProduction'))->toBe([]);
});

it('does not show orders that are not yet paid', function () {
    $order = ($this->makeOrder)(OrderStatus::PendingPayment, ['paid_at' => null]);

    livewire(Workshop::class)->assertDontSee($order->order_number);
});

it('does not show shipped orders', function () {
    $order = ($this->makeOrder)(OrderStatus::Shipped);

    livewire(Workshop::class)->assertDontSee($order->order_number);
});

it('shows the monogram on the card', function () {
    ($this->makeOrder)(OrderStatus::Paid);

    livewire(Workshop::class)->assertSee('MA');
});

it('counts days waiting from paid_at, not created_at', function () {
    $order = ($this->makeOrder)(OrderStatus::Paid, ['paid_at' => now()->subDays(5)]);
    $order->update(['created_at' => now()->subDays(30)]);

    livewire(Workshop::class)->assertSee('5 days');
});

it('moves an order across when the card action is used', function () {
    $order = ($this->makeOrder)(OrderStatus::Paid);

    livewire(Workshop::class)
        ->callAction('start_production', arguments: ['order' => $order->id]);

    expect($order->fresh()->status)->toBe(OrderStatus::InProduction);
});

it('shows the number strip', function () {
    ($this->makeOrder)(OrderStatus::PendingPayment, ['paid_at' => null]);
    ($this->makeOrder)(OrderStatus::Paid, ['paid_at' => now()->subDays(9)]);

    livewire(Workshop::class)
        ->assertSet('awaitingPayment', 1)
        ->assertSet('overdue', 1);
});

it('excludes cancelled and refunded orders from this month\'s revenue', function () {
    ($this->makeOrder)(OrderStatus::Paid, ['paid_at' => now(), 'total_minor' => 5000]);
    ($this->makeOrder)(OrderStatus::Refunded, ['paid_at' => now(), 'total_minor' => 9900]);
    ($this->makeOrder)(OrderStatus::Cancelled, ['paid_at' => now(), 'total_minor' => 9900]);

    livewire(Workshop::class)->assertSet('revenueThisMonthMinor', 5000);
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=WorkshopTest`
Expected: FAIL — `App\Filament\Pages\Workshop` not found.

- [ ] **Step 3: Write the page class**

Create `app/Filament/Pages/Workshop.php`:

```php
<?php // app/Filament/Pages/Workshop.php

namespace App\Filament\Pages;

use App\Domain\Order\Models\Order;
use App\Domain\Order\OrderStatus;
use App\Filament\Actions\TransitionActions;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class Workshop extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-scissors';

    protected static ?string $title = 'Workshop';

    protected static ?int $navigationSort = -1;

    protected string $view = 'filament.pages.workshop';

    public Collection $toMake;
    public Collection $inProduction;
    public Collection $readyToPost;

    public int $awaitingPayment = 0;
    public int $overdue = 0;
    public int $revenueThisMonthMinor = 0;

    public function mount(): void
    {
        $this->loadQueue();
    }

    public function loadQueue(): void
    {
        // eager-load items: the cards read the snapshot, never the live catalogue
        $base = fn () => Order::with('items')->orderBy('paid_at');

        $this->toMake = $base()->where('status', OrderStatus::Paid)->get();

        $inProduction = $base()->where('status', OrderStatus::InProduction)->get();

        $this->inProduction = $inProduction->whereNull('ready_at')->values();
        $this->readyToPost = $inProduction->whereNotNull('ready_at')->values();

        $this->awaitingPayment = Order::where('status', OrderStatus::PendingPayment)->count();

        $this->overdue = Order::whereIn('status', [OrderStatus::Paid, OrderStatus::InProduction])
            ->where('paid_at', '<', now()->subDays(7))
            ->count();

        $this->revenueThisMonthMinor = (int) Order::whereNotIn('status', [
                OrderStatus::PendingPayment,
                OrderStatus::Cancelled,
                OrderStatus::Refunded,
            ])
            ->where('paid_at', '>=', now()->startOfMonth())
            ->sum('total_minor');
    }

    protected function getHeaderActions(): array
    {
        return [
            TransitionActions::startProduction()->record(fn (array $arguments) => Order::find($arguments['order'])),
            TransitionActions::markReady()->record(fn (array $arguments) => Order::find($arguments['order'])),
            TransitionActions::ship()->record(fn (array $arguments) => Order::find($arguments['order'])),
        ];
    }
}
```

The actions are registered once on the page and invoked per card with `wire:click="mountAction('start_production', { order: {{ $order->id }} })"`. After each action, call `$this->loadQueue()` so the card moves column without a page reload — add `->after(fn (Workshop $livewire) => $livewire->loadQueue())` to each action registration here rather than inside `TransitionActions`, which Task 4's order page also uses.

- [ ] **Step 4: Write the Blade view**

Create `resources/views/filament/pages/workshop.blade.php`. It renders three columns and a number strip. The card partial, repeated per column:

```blade
@php
    $daysWaiting = $order->paid_at?->diffInDays(now()) ?? 0;
    $urgency = match (true) {
        $daysWaiting >= 7 => 'text-danger-600 dark:text-danger-400',
        $daysWaiting >= 3 => 'text-warning-600 dark:text-warning-400',
        default => 'text-gray-500 dark:text-gray-400',
    };
@endphp

<div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
    <div class="flex items-baseline justify-between">
        <span class="font-mono text-sm text-gray-500">{{ $order->order_number }}</span>
        <span class="text-xs font-medium {{ $urgency }}">{{ $daysWaiting }} days</span>
    </div>

    @foreach ($order->items as $item)
        <div class="mt-3">
            <div class="font-medium text-gray-950 dark:text-white">{{ $item->product_name }}</div>
            <div class="text-sm text-gray-500">{{ $item->variant_description }}</div>

            @if (filled($item->personalization))
                @foreach ($item->personalization as $key => $value)
                    <div class="mt-2 rounded-lg bg-amber-50 px-3 py-2 dark:bg-amber-950/40">
                        <div class="text-xs uppercase tracking-wide text-amber-700 dark:text-amber-500">{{ str($key)->headline() }}</div>
                        <div class="font-mono text-2xl font-bold tracking-widest text-amber-950 dark:text-amber-200">{{ $value }}</div>
                    </div>
                @endforeach
            @endif
        </div>
    @endforeach

    <div class="mt-3 text-sm text-gray-500">To {{ $order->country_code }}</div>

    <button
        type="button"
        wire:click="mountAction('{{ $action }}', { order: {{ $order->id }} })"
        class="mt-4 w-full rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-500"
    >
        {{ $actionLabel }}
    </button>
</div>
```

The number strip below the columns shows `$awaitingPayment` ("awaiting payment"), `$overdue` ("waiting over 7 days"), and `Money::format($revenueThisMonthMinor)` ("this month"). No chart, no polling — a manual "Refresh" button calling `loadQueue()`.

- [ ] **Step 5: Make Workshop the panel home**

In `app/Providers/Filament/AdminPanelProvider.php`, replace the default `->pages([Dashboard::class])` with `->pages([Workshop::class])` and set `->homeUrl('/admin')` if needed so `/admin` lands on the Workshop.

Update `tests/Feature/Admin/PanelAccessTest.php`'s "lets an operator in" expectation only if the redirect target changes; `assertSuccessful()` should still hold.

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=WorkshopTest`
Expected: PASS, 9 tests.

- [ ] **Step 7: Run the full suite and commit**

Run: `php artisan test`

```bash
git add app/Filament resources/views/filament tests/Feature/Admin
git commit -m "feat: add workshop dashboard as the admin landing page"
```

---

## Task 6: Deployment and login hardening

**Files:**
- Modify: `.gitignore`
- Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Create: `deploy.md`
- Create: `tests/Feature/Admin/LoginThrottleTest.php`

**Interfaces:**
- Consumes: the panel from Task 2.
- Produces: nothing.

- [ ] **Step 1: Write the failing throttle test**

Create `tests/Feature/Admin/LoginThrottleTest.php`:

```php
<?php // tests/Feature/Admin/LoginThrottleTest.php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('admin-login|127.0.0.1');
});

it('rate limits repeated login attempts', function () {
    User::factory()->create(['email' => 'owner@example.com', 'is_operator' => true]);

    foreach (range(1, 5) as $attempt) {
        $this->post('/admin/login', [
            'email' => 'owner@example.com',
            'password' => 'wrong-password',
        ]);
    }

    $this->post('/admin/login', [
        'email' => 'owner@example.com',
        'password' => 'wrong-password',
    ])->assertStatus(429);
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=LoginThrottleTest`
Expected: FAIL — the sixth attempt returns 302, not 429.

- [ ] **Step 3: Add the rate limiter**

In `app/Providers/AppServiceProvider.php::boot()`:

```php
        RateLimiter::for('admin-login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
```

In `AdminPanelProvider`, add `'throttle:admin-login'` to the panel's middleware for the auth routes. Filament v5 exposes this via `->middleware([...])` on the panel; if the login route cannot be targeted specifically, register a route middleware on `filament.admin.auth.login` in `bootstrap/app.php` and note the deviation in the report.

Also add failed-attempt logging by listening for `Illuminate\Auth\Events\Failed` in `AppServiceProvider`:

```php
        Event::listen(Failed::class, fn (Failed $event) => Log::warning('Failed admin login', [
            'email' => $event->credentials['email'] ?? null,
            'ip' => request()->ip(),
        ]));
```

- [ ] **Step 4: Run it to verify it passes**

Run: `php artisan test --filter=LoginThrottleTest`
Expected: PASS.

- [ ] **Step 5: Un-ignore the compiled assets**

In `.gitignore`, delete the line `/public/build`.

Then:

```bash
npm install
npm run build
```

- [ ] **Step 6: Write deploy.md**

Create `deploy.md` at the repo root:

````markdown
# Deploying

The production host is Azerbaijani shared hosting: local disk persists, cron is
available, there are no long-running queue workers, and Node is not installed.

## Before every deploy commit

`/public/build` is **tracked**, because the host cannot run a build step. Compiled
assets must be regenerated and committed whenever CSS, JS, or Filament changes:

```bash
npm run build
git add public/build
git commit -m "chore: rebuild assets"
```

Forgetting this ships an unstyled panel.

## On the server

```bash
git pull
php artisan migrate --force
php artisan filament:assets
php artisan storage:link      # first deploy only
php artisan config:cache
php artisan route:cache
```

## Cron

There is no queue worker. Add one entry:

```
* * * * * cd /path/to/leather-shop && php artisan schedule:run >> /dev/null 2>&1
```

This runs `ReleaseExpiredReservations` and processes queued mail.

## Environment

`APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, the host's
MySQL credentials, and `SHOP_OPERATOR_EMAIL` set to a real inbox.

## First run

```bash
php artisan shop:make-admin
```

Then sign in at `/admin`.
````

- [ ] **Step 7: Run the full suite and commit**

Run: `php artisan test`
Expected: green, output pristine.

```bash
git add .gitignore public/build deploy.md app tests/Feature/Admin/LoginThrottleTest.php
git commit -m "chore: track compiled assets, rate limit admin login, document deployment"
```

---

## Open item carried from Plan 1

`OversellTest` has still never run against MySQL — `lockForUpdate()` is a silent no-op on SQLite, so oversell protection is verified by code reading only. **This does not block 2A.** It must run before the shop takes a real payment:

```
docker compose up -d mysql
$env:DB_CONNECTION="mysql_test"; php artisan test --filter=OversellTest
```

Task 1's `restoreCapacity()` uses the same `lockForUpdate()` pattern and inherits the same caveat.
