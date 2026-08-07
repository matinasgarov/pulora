# Hand-Crafted Leather Goods E-Commerce — Design

**Date:** 2026-08-08
**Status:** Approved (design), pending implementation plan

## 1. Context and Goals

A real, revenue-taking online store selling hand-crafted leather wallets and related
goods. The business operates from Azerbaijan and ships worldwide.

**Operator:** technically capable, but wants a strong admin UI rather than editing the
database or deploying to change a price.

**Priority (stated):** own the codebase. No platform lock-in, no per-order SaaS cut.

### Success criteria

- A customer anywhere in the world can select a wallet, choose leather and thread
  colour, add a monogram, pay by card, and receive an order confirmation.
- The operator can add a product, upload photos, set stock, and mark an order shipped
  without touching code.
- Every order records exactly what was bought, at the price paid, with the
  personalization text needed to make it.
- The store is fully exercisable end to end before a real payment gateway exists.

## 2. Hard Constraint: Payments from Azerbaijan

This constraint shaped the whole design and is recorded here so it is not rediscovered
later.

- **Stripe does not support Azerbaijan-based merchants.** Stripe operates in 46
  countries; Azerbaijan is not among them.
- **PayPal in Azerbaijan is receive-limited** and is not a viable way to collect
  revenue.
- **Viable path: a local acquirer** — Epoint, AzeriCard, or Payriff. All three process
  Visa/Mastercard issued by foreign banks, which is what makes worldwide selling work.
- **All of them require a registered business and a bank contract** before issuing live
  API keys. The operator intends to register but has not yet.

**Consequence:** payments are behind an adapter interface from day one. The entire shop
is built and tested against a mock gateway; the real gateway is a later, isolated,
low-risk change. Gateway paperwork proceeds in parallel with the build instead of
blocking it.

**Currency:** AZN only in v1. Foreign customers are charged in AZN and their issuing
bank converts. Multi-currency display is deferred.

## 3. Architecture

One Laravel 12 application, PHP 8.5, with two front doors sharing one database and one
set of domain services.

- **Storefront** (`/`) — public, server-rendered Blade + Tailwind. Listing, product
  detail with variant and personalization selection, cart, checkout, confirmation, and
  order lookup by email plus order number. **No customer accounts in v1.**
- **Admin** (`/admin`) — Filament panel behind authentication. Products, variants,
  images, orders, shipping zones, discount codes.

### Domain services

Extracted from controllers because this is where defects concentrate.

| Service | Responsibility | Key property |
|---|---|---|
| `CartService` | Line items, personalization data, price recalculation | Never trusts a price supplied by the browser |
| `OrderService` | Cart → immutable order; stock decrement; confirmation email | Idempotent — payment callbacks repeat |
| `ShippingCalculator` | Destination zone + weight → rate | Isolated; shipping rules change often |

### Payment boundary

Neither storefront nor admin talks to a gateway directly. Both go through:

```php
interface PaymentGateway {
    public function createPayment(Order $order): PaymentRedirect;
    public function verifyCallback(Request $request): CallbackResult;
    public function refund(Order $order, int $amountMinor): RefundResult;
}
```

Implementations: `MockGateway` (development, CI) and `EpointGateway` (production).
Selected by configuration. Swapping is one config value and one class.

### Out of scope for v1

Customer accounts, wishlists, product reviews, multi-currency, blog/CMS, abandoned-cart
email, admin analytics. Each is straightforward to add against this structure; none
earns its build cost before the first hundred orders.

## 4. Data Model

Money is stored as **integers in minor units** (qəpik). Never floats — floating-point
money produces rounding defects that surface as irreproducible customer complaints.

- **`products`** — name, slug, description, craft/story copy, base price, active flag,
  lead-time days.
- **`product_images`** — product_id, path, alt text, sort order. Separate table:
  leather sells on photography, so expect 6–8 reorderable images per product.
- **`variants`** — product_id, SKU, nullable price override, stock quantity, **shipping
  weight in grams**, active flag. **Every product has at least one variant**, even a
  plain one. This uniformity removes a class of null-checks from cart and checkout.
  Weight lives on the variant rather than the product because a larger size genuinely
  weighs more, and `ShippingCalculator` brackets on total order weight.
- **`variant_options`** / **`option_values`** — leather colour, thread colour, size.
  Relational rather than a JSON blob, so Filament renders real dropdowns and stock is
  filterable by option.
- **`personalization_options`** — product_id, type (`monogram`, `gift_wrap`,
  `custom_stamp`), label, price delta, max characters, allowed-character pattern.
  Per-product, because not every item accepts a monogram.
- **`orders`** — order number, customer email and name, shipping address, status,
  subtotal, shipping cost, discount, total, currency, payment reference, customs
  contents and declared value, **`source`** (`web` | `shopify` | `manual`, default
  `web`), timestamps.
  Status enum: `pending_payment → paid → in_production → shipped → delivered`, plus
  `cancelled` and `refunded`.
- **`order_items`** — a **snapshot**: product name, variant description, unit price,
  personalization text, copied at purchase time rather than joined live. When prices
  change, historical orders must still show what the customer actually paid. Keeps a
  nullable product_id for convenience links but never depends on it.
- **`shipping_zones`** / **`shipping_rates`** — zone (country list); rate by weight
  bracket or flat.
- **`discount_codes`** — code, percent or fixed, minimum order, usage limit, **times
  used** (incremented inside the same transaction as `markPaid`, so a usage limit
  cannot be exceeded by concurrent checkouts), expiry.
- **`payment_logs`** — every gateway request and callback, raw. This table is the only
  thing that resolves "I was charged and got nothing."

## 5. Checkout and Payment Flow

Checkout is **one page, guest only**: contact email, shipping address, shipping method
with rates recalculated live as the country changes, order summary, pay.

1. Customer submits checkout. The server **recalculates every price from the database**
   — item prices, personalization deltas, shipping, discount. Browser-supplied numbers
   are a suggestion, never truth. This is the primary defence against tampering.
2. `OrderService` creates the order as `pending_payment` and reserves stock. The order
   number is generated now, so the customer has a reference even if payment fails.
3. `PaymentGateway::createPayment()` returns a redirect URL. The customer leaves for the
   gateway's hosted page. **Card details never touch our server**, keeping PCI scope at
   the minimum self-assessment questionnaire rather than a compliance project.
4. The gateway redirects the customer back *and* independently calls a server-to-server
   callback. **Only the callback is trusted** — the browser redirect is a UX
   convenience and is trivially forged.
5. `verifyCallback()` checks the signature, then `OrderService::markPaid()` runs
   **idempotently**: three callbacks produce one confirmation email and one stock
   decrement.

### Failure handling

| Failure | Behaviour |
|---|---|
| Payment abandoned | Order stays `pending_payment`; stock reservation expires after 30 minutes via scheduled job; cart preserved |
| Invalid callback signature | Log loudly, leave order untouched, alert operator |
| Stock exhausted between cart and payment | Fail **before** redirecting to the gateway — never after taking money |
| Gateway unreachable | Customer-facing message explaining the order is saved and unpaid, with a retry link |

## 6. Admin and Operations

Filament panel. Products with inline variants, drag-to-reorder image gallery,
per-product personalization rules.

Orders list filtered by status. The order detail view is built around the workbench:

- **Production worksheet** — variant, monogram text, lead time; printable.
- **Status transitions as buttons.** Marking shipped prompts for a tracking number and
  emails the customer.
- **Low-stock highlighting** on the variant list.
- **Customs export** — contents description and declared value, as a CN22 requires.

Filament supplies roughly 80% of this from resource definitions. The worksheet and the
ship action are custom.

### Shipping

Weight-bracketed rates per zone, defined by the operator in admin — **not** live carrier
APIs. Live lookups require carrier accounts that do not exist yet, and for a small
catalogue with predictable weights an operator-controlled table is more accurate and far
less fragile. Launch zones: Azerbaijan, regional, rest-of-world.

## 7. Testing Strategy

The pyramid follows risk, not code volume.

- **Unit** — `ShippingCalculator` zone and weight boundaries, discount arithmetic,
  minor-unit money math. Cheap and exhaustive.
- **Feature** — the money paths:
  - recalculation rejects a tampered price;
  - checkout fails cleanly when stock is exhausted;
  - **the same callback delivered twice yields exactly one paid order**;
  - an invalid signature changes nothing.
- **Gateway** — `MockGateway` runs the full purchase path in CI with no network.
  `EpointGateway` gets contract tests against recorded fixtures once real API
  documentation is available.

### Error surface

Customer-facing failures state what happened and what to do next, never a stack trace.
Payment and callback anomalies write to `payment_logs` and notify the operator by email
— a silent payment failure is a lost sale nobody learns about.

## 8. Future: Shopify as a Sales Channel

Not built in v1. Recorded so v1 does not foreclose it.

**Intended shape:** this site remains the primary store; Shopify becomes an additional
channel for reach. A background sync service pushes products and stock to the Shopify
Admin API and pulls orders into `OrderService`.

**Economic note:** Shopify Payments is not available in Azerbaijan, so a Shopify store
there runs on a third-party gateway and incurs Shopify's **0.5–2% third-party
transaction fee on top of** the gateway's own fee, plus the monthly plan. This argues
against Shopify as the primary store; it does not rule it out as a channel.

**The rule that must hold:** **one system owns stock.** Laravel is the source of truth
and pushes stock outward — never bidirectional. Two channels selling the same
one-of-a-kind wallet is how a customer receives an apology instead of a product.

**Affordances already in v1:** stable per-variant SKUs as the external join key, stock
changes flowing exclusively through `OrderService`, and the `orders.source` column.

## 9. Sequencing Note

Gateway registration (business registration → bank contract → Epoint/AzeriCard/Payriff
API keys) runs in parallel with the build and is the long pole. The build does not wait
on it; `MockGateway` keeps every other decision testable.
