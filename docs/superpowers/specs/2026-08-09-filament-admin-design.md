# Plan 2A — Filament Admin: Design

**Date:** 2026-08-09
**Status:** Approved (design)
**Depends on:** [Commerce engine design](2026-08-08-leather-goods-ecommerce-design.md), merged as Plan 1 at `master@9c94702`
**Siblings:** 2B storefront (separate spec), 2C EpointGateway (deferred until API credentials exist)

## 1. Purpose

Plan 1 built the buy side: catalogue, cart, discounts, shipping, order creation,
and payment callback handling. Nothing in it can move an order forward. The
`OrderStatus` enum defines `in_production`, `shipped`, `delivered`, `cancelled`
and `refunded`, and no code reaches any of them.

Plan 2A delivers the operator's half of the shop: the order lifecycle in the
domain, and a Filament panel to drive it. The shop is **make to order** — every
piece is cut after the order arrives — so the admin's daily job is a making
queue, not an inventory screen.

## 2. Scope

**In scope**

- A Filament v5 panel at `/admin`, single operator, session auth.
- `OrderService::transition()` plus supporting schema: the order lifecycle.
- Six resources: Product (with Variant, Image, Option relation managers),
  Order, ShippingZone (with Rate relation manager), DiscountCode, PaymentLog.
- A custom Workshop dashboard as the panel's landing page.
- Image upload to local disk.
- Deployment notes for Azerbaijani shared hosting.

**Out of scope, deliberately**

Storefront pages and cart routes (2B). The Epoint gateway (2C). Customer
accounts — checkout stays guest-only. Roles and permissions — there is one
operator. Analytics dashboards, CSV import/export, and multi-currency.

## 3. Panel, access, and boundaries

A single Filament panel at `/admin`, guarded by Laravel session auth against the
existing `users` table. `User` implements `FilamentUser::canAccessPanel()`.
Nothing else about Plan 1's models changes.

**One admin account, created by artisan:** `php artisan shop:make-admin`.
Public registration stays disabled. A publicly registrable admin panel on a shop
that takes money is an open door.

**The panel is a presentation layer over Plan 1's domain, not a second
implementation of it.** Catalogue data — products, variants, images, zones,
rates, codes — is genuinely CRUD, and resources read and write those models
directly. Anything touching **money or stock** goes through the domain services.
Marking an order shipped, cancelling, or refunding calls `OrderService`; no
admin code path writes `orders.status` directly. Otherwise the idempotency and
row locking Plan 1 spent three tasks getting right would be bypassed by a button.

## 4. Order lifecycle (domain work)

### 4.1 `OrderService::transition()`

```php
public function transition(Order $order, OrderStatus $to, ?string $note = null): void
```

One guarded entry point. Runs inside a database transaction. Throws
`App\Domain\Order\IllegalTransitionException` on any move not in the table below,
without writing the row.

### 4.2 Legal transitions

| From | To |
|---|---|
| `paid` | `in_production`, `cancelled`, `refunded` |
| `in_production` | `shipped`, `cancelled`, `refunded` |
| `shipped` | `delivered`, `refunded` |
| `delivered` | `refunded` |
| `cancelled` | *(terminal)* |
| `refunded` | *(terminal)* |

**No admin action may set an order *to* `paid` or *to* `pending_payment`** —
neither appears in the `To` column above. Only the payment callback may create
`paid`; only `ReleaseExpiredReservations` may retire an unpaid order. Moving
*out of* `paid` is the admin's job and is what the first row permits. Admin-settable `paid` would put a UI-shaped hole in
Plan 1's rule that only the server-to-server callback is trusted.

### 4.3 Stock consequences

Plan 1 decrements `variants.stock_quantity` at order creation and restores it on
reservation expiry. Under make-to-order, that column is a **capacity cap the
operator sets by hand** — how many of a variant they are willing to commit to —
not shelf inventory. The mechanics are unchanged; only the meaning is.

- **Cancel** restores capacity for every order item, inside the transaction,
  using the same `lockForUpdate()` discipline as `createFromCart()`. Without
  this, the cap drifts permanently downward with every cancellation.
- **Refund** does **not** restore capacity by default — the piece is usually
  already cut. The refund action carries an explicit `restoreCapacity` boolean,
  defaulting to false, for the cases where it should.

### 4.4 Side effects

- `shipped` requires a tracking number and sends `ShipmentNotification` to the
  customer's email.
- `refunded` calls `PaymentGateway::refund()` and records a `PaymentLog` row.
  The admin action surfaces the gateway's actual `RefundResult` to the operator;
  it never assumes success. `MockGateway` always succeeds, which is precisely
  why the real result must be displayed rather than inferred.
- Every transition appends an `order_events` row. `transition()` writes it; no
  UI code does.

### 4.5 Schema changes

`orders` **already has** `tracking_number` and `shipped_at` — both were created by
Plan 1's `create_order_tables` migration, and `shipped_at` is already cast on the
`Order` model. Nothing written them yet; `transition()` is what finally populates
them.

`orders` gains exactly one column:

| Column | Type | Notes |
|---|---|---|
| `ready_at` | timestamp, nullable | set by the "Mark made" action (§5.1); not a status change |

New `order_events` table:

| Column | Type |
|---|---|
| `id` | pk |
| `order_id` | fk → orders, cascade delete |
| `from_status` | string |
| `to_status` | string |
| `note` | text, nullable |
| `user_id` | fk → users, nullable (null for system transitions) |
| `created_at` | timestamp |

Append-only. It is the answer when a customer asks where their wallet is.

## 5. The Workshop dashboard

A custom Filament page, registered as the panel's landing page at `/admin`.
Filament's default dashboard is a grid of stat widgets; this one answers **what
to make today**.

### 5.1 Layout

Three columns by status:

1. **To make** — `paid`
2. **In production** — `in_production`
3. **Ready to post** — finished, not yet handed to the post office

"Ready to post" is a UI bucket over `in_production`, distinguished by a
`ready_at` timestamp on the order (nullable, set by the "Mark made" action).
It is not a new `OrderStatus` value — the status enum stays as Plan 1 defined it,
and the domain transition to `shipped` still happens once, at the post office.

### 5.2 Card contents

Order number; product and variant name; **personalization text rendered large**
— the monogram is what gets misread at 8am, so it carries typographic weight
rather than sitting in a small grey label; destination country; and **days
waiting**, amber past 3 days, red past 7. One primary button per card calls the
matching transition and moves the card one column right.

Two constraints:

- The queue reads from **`order_items`**, Plan 1's snapshots — never joined live
  to the catalogue. A card shows what the customer actually bought, even after a
  product is renamed.
- **Days waiting counts from `paid_at`, not `created_at`.** An order that sat
  unpaid for two days is not late.

### 5.3 Number strip

Below the columns, three figures and no chart: orders awaiting payment now,
unfulfilled orders older than 7 days, and revenue this month. Three numbers read
faster than a chart, and a chart would mean shipping a JS bundle.

### 5.4 No polling

The page loads on request with a manual refresh. Filament's live polling costs a
request every few seconds; with one operator, who is the only person changing
this data, it cannot go stale behind their back.

## 6. Resources

### 6.1 ProductResource

One edit page, tabbed: **Details** (name, slug, description, active),
**Images**, **Options**, **Variants**.

- Slug auto-fills from the name, stays editable, and becomes **immutable once
  the product has been ordered** — a changed slug is a dead link and a lost sale.
- Price is entered in **manats with two decimals** and converted to
  `base_price_minor` on save. The form never shows a raw qəpik integer and never
  round-trips through a float: conversion is string-parsed.
- Variants are a relation manager on the same page. A product with no variants
  is not sellable and should not be two navigations away.

### 6.2 Variants

A relation manager under Product, not a standalone navigation item. Columns:
SKU, options, price override, `stock_quantity` — **labelled "Capacity"** in the
UI, with a hint reading *how many of this you are willing to commit to*.
Inline-editable from the table, as it is the most frequent edit.

### 6.3 OrderResource

**Read-mostly by design.** No create action; no editable fields on the order.
The view page shows snapshot line items, the shipping address, the payment log,
and the `order_events` history, plus the same transition actions the Workshop
dashboard exposes. An order that can be hand-edited is an order whose totals no
longer match what the customer was charged.

### 6.4 ShippingZoneResource

Zones with rates as a relation manager.

### 6.5 DiscountCodeResource

Full CRUD except `times_used`, which is a **read-only column**. It is owned by
`DiscountService::consume()`'s conditional atomic increment; a form that could
write it would reintroduce the usage-limit race Plan 1 closed.

### 6.6 PaymentLogResource

Read-only, list and view only. For when a payment goes strange and the raw
gateway record is needed.

### 6.7 Images

`FileUpload` to `storage/app/public/products` with the standard `storage:link`
symlink. No object storage — the point of the shared-hosting decision is that
local disk persists.

## 7. Assets and deployment

**Target:** Azerbaijani shared hosting. Local disk persists, cron is available,
long-running queue workers are not, and Node is unlikely.

Filament publishes its own compiled assets via `php artisan filament:assets` —
a file copy, no Node required. The project's own Tailwind build is the problem:
`/public/build` is currently in `.gitignore`, so a `git pull` deploy would ship
an unstyled panel.

**Decision: un-ignore `/public/build` and commit compiled assets.** A `deploy.md`
in the repo records that `npm run build` runs before any deploy commit. Deployment
is then:

```
git pull && php artisan migrate --force && php artisan filament:assets
```

It costs one line of `.gitignore` and no infrastructure. When the shop outgrows
shared hosting, CI replaces this without touching application code.

**Production configuration**

- `APP_ENV=production`, `APP_DEBUG=false`, MySQL credentials from the host.
- HTTPS-only panel; `session.secure=true`.
- `ReleaseExpiredReservations` runs from the host's cron
  (`* * * * * php artisan schedule:run`), not a queue worker.
- **The admin login route is rate-limited and failed attempts are logged.**
  A public `/admin` on a shop taking real money is found by scanners within days
  of the domain going live.

## 8. Testing

**`transition()` gets Plan 1's full treatment** — it is money and stock:

- One test per legal transition; one per illegal transition asserting
  `IllegalTransitionException` and that no row was written.
- Cancel restores capacity; refund does not, unless `restoreCapacity` is set.
- `order_events` records the correct from/to pair and acting user.
- No admin path can reach `paid` or `pending_payment`.

**Filament resources get smoke tests**, via Livewire's test helpers: page loads,
table lists records, form saves valid input, form rejects invalid input. The one
place with real assertions is the **manat↔qəpik conversion** — entering `49.99`
stores `4999`, and editing then re-saving without touching the field does not
drift the value. Round-tripping money through a form is where a shop silently
starts charging the wrong price.

**Two access tests:** an unauthenticated request to `/admin` redirects to login
rather than rendering; a `User` failing `canAccessPanel()` is refused.

**The Workshop dashboard gets one behavioural test:** given orders in each
status, the page renders each in the correct column and a transition button
moves one across. No pixel assertions.

**Not tested:** Filament's own framework behaviour, form field rendering
details, CSS. Those belong to the vendor.

## 9. Carried-forward open item

`OversellTest` has still never executed against MySQL — Docker was unavailable
during Plan 1, and `lockForUpdate()` is a silent no-op on SQLite. Oversell
protection is currently verified by code reading only.

```
docker compose up -d mysql
$env:DB_CONNECTION="mysql_test"; php artisan test --filter=OversellTest
```

This must run **before the shop takes a real payment**. It does not block 2A
from merging. Note also that even a passing run proves stock exhaustion rather
than true lock contention.

## 10. Sequencing

1. Order lifecycle: schema, `transition()`, tests. Pure domain, no Filament.
2. Panel installation, `canAccessPanel()`, `shop:make-admin`, access tests.
3. Catalogue resources: Product with its relation managers.
4. Order, ShippingZone, DiscountCode, PaymentLog resources.
5. Workshop dashboard.
6. Deployment: `/public/build`, `deploy.md`, login rate limiting.

Steps 3 and 4 depend on 2; everything depends on 1.
