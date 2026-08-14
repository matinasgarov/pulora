# Storefront (Plan 2B) Design Spec

**Date:** 2026-08-14
**Status:** Approved for planning

## Goal

Give the shop a customer-facing storefront in Azerbaijani and English, so that
someone can find a product, choose its options, and buy it.

Today they cannot. The commerce engine is complete and tested and the operator's
admin panel is production-shaped, but there is no shop front attached to either:
`/` serves Laravel's stock welcome page, **no cart route exists at all**, and
`CheckoutController::store()` — which begins by reading the session cart — is
therefore unreachable, since nothing can ever put a line in that cart.

## Context

**What already exists** (Plan 1, merged; Plan 2A, merged):

- `CartService::add(variantId, quantity, personalization)`, `remove()`,
  `clear()`, `snapshot()` — session-backed, and `add()` already validates
  personalization against the product's own options through a domain validator.
- `ShippingCalculator::quotesFor(country, weight)` and `quoteById(...)`.
- `DiscountService`, `OrderService::createFromCart()`, `PaymentGateway` (mock
  driver only), signature-verified `POST /payment/callback`.
- `CheckoutController@store`, `CheckoutConfirmationController`,
  `OrderLookupController` — working, tested, and rendering unstyled raw HTML.
- A Filament admin at `/admin` with the full catalogue and order lifecycle.
- Tailwind 4 + Vite configured; `public/build` committed for the Node-less host.

**What this spec adds:** routes, controllers, Livewire islands, views, a design
system, and bilingual content.

## Scope

This spec covers **Plan 2B**: the bilingual foundation, the design system, and
the complete buy path. It ends with a customer able to buy in either language.

**Deferred to Plan 2C:** the editorial home page, an about/craft page, and
presentation of each product's `story` field. Plan 2B ships the catalogue as the
landing page so it is shippable on its own rather than leaving a placeholder.

**Explicitly out of scope:** customer accounts (the shop is guest-checkout only
by design), categories or collections (premature for a handful of hand-made
products), search, SEO metadata, and any real payment driver.

## Global constraints

Inherited, and binding on every task:

- Money is always an integer in minor units (qəpik); conversion is string-parsed,
  never `(int) ($value * 100)`. Every price passes through `App\Support\Money`
  for display and `App\Support\MoneyInput` for entry.
- Currency is AZN only.
- `order_items` are snapshots. They are never joined live to the catalogue.
- `variants.stock_quantity` is a capacity cap the operator sets by hand, not
  shelf inventory.
- Guest checkout only. The `users` table holds operators.
- No code path writes `orders.status` directly; every change goes through
  `OrderService::transition()`.

New, and specific to this plan:

- **The storefront never re-implements a domain decision.** Prices, shipping
  quotes, discount validity and personalization rules are resolved server-side
  by the existing services. The UI renders their answers.
- **One write path per operation.** Where two entry points need the same
  behaviour, they call one service.

## 1. Bilingual foundation

### Translatable content

These columns become JSON, holding `{"en": "…", "az": "…"}`:

| Table | Columns |
|---|---|
| `products` | `name`, `description`, `story` |
| `variants` | `description` |
| `product_images` | `alt_text` |
| `variant_options` | `name` |
| `option_values` | `value` |
| `personalization_options` | `label` |

Option names and values are included because they are customer-visible — a
colour reading "Cognac" must be translatable like any other copy.

`products.slug` stays single-language. It is a URL, and it is already locked
once a product has been ordered.

### Resolution

A small in-house `HasTranslations` trait: read the active locale, fall back to
the default locale when that locale's value is empty. `spatie/laravel-translatable`
is deliberately not used — the requirement is get/set with fallback, and this
codebase consistently prefers its own small domain pieces to dependencies.

### Migration

Existing rows hold bare strings. The migration wraps each as
`{"en": "<existing value>"}` and reverses by extracting `en`, so it is reversible
in both directions.

### Routing

Locale lives in the URL: `/en/...` and `/az/...`, applied by a `SetLocale`
middleware. Session-only switching was rejected: it makes every page unshareable
and leaves one language invisible to search engines.

**Default and fallback are both `en`**, matching the current `config/app.php`
and the migration, which writes existing content into the `en` key. `/`
therefore redirects to `/en`, and a missing Azerbaijani value falls back to
English.

This is deliberate but temporary: until Azerbaijani copy is actually written,
landing on `/az` would show a page that falls back to English on every single
field, which reads as broken rather than bilingual. Once the Azerbaijani content
exists, making it the landing locale is a one-line change to `APP_LOCALE` and
the `/` redirect. The switcher exposes both languages from day one regardless.

**`POST /payment/callback` stays outside the locale prefix.** It is machine-facing
and signature-verified; prefixing it would break the gateway's callback URL.

### Order snapshots

`order_items` freeze what the customer actually saw, so they store the resolved
string in the locale of purchase. `orders` gains a `locale` column.

This keeps the existing admin display correct with no changes, and lets
confirmation and shipment emails go out in the language the customer ordered in.

### Admin impact

This edits Plan 2A. `ProductResource` gains per-locale fields (a tab per
language), as do the variants relation manager and the personalization labels.
Validation requires the default locale and allows the other to be blank, falling
back at render time.

## 2. Design system

The reference is Loro Piana: quiet luxury, not fast fashion.

### Tokens

Added to Tailwind 4's `@theme`:

- **Ground:** warm ivory, approximately `#F6F3EE` — not white.
- **Tile:** a half-step deeper greige, approximately `#EDEAE4`, so product
  photography on a neutral seamless background blends into one field.
- **Ink:** deep navy-black.
- **Accent:** a single oxblood, approximately `#8B3A2E`, used sparingly — the
  wordmark, the active nav item, a product's material line. One accent used
  rarely is what reads as restraint.

### Typography

Serif for wordmark, navigation, headings and product names. The existing
Instrument Sans is retained for form fields, validation messages and cart
totals, where a high-contrast serif costs legibility.

**Blocking constraint:** Azerbaijani requires `ə` (U+0259) as well as
`ğ ı ö ş ü ç`. Many elegant display serifs omit the schwa. **The chosen face
must be validated against the full Azerbaijani character set before it is
adopted** — a face that cannot set the language is disqualified regardless of
how it looks.

Fonts are self-hosted through `@fontsource` and bundled by Vite, not loaded from
a CDN, since `public/build` is already committed for the Node-less host.

### Components

Announcement bar; header (wordmark, navigation, locale switcher, cart count);
footer; hero with offset card; product tile; product grid; price; button; form
field; quantity stepper.

### Layout

The grid is edge-to-edge — four across on desktop, two on tablet, one on mobile
— with small gutters and heavy padding *inside* each tile, so the product floats
in its frame. That internal emptiness is the effect; widening the gutters is not.

### Motion

Hero crossfade, a second-image swap on tile hover, and a gentle reveal on
scroll. All of it honours `prefers-reduced-motion`, and no content is gated
behind JavaScript.

## 3. The buy path

### Routes

Under the `/{locale}` prefix: catalogue (the landing page for 2B),
`product/{slug}`, `cart`, `checkout`, and the existing confirmation and
order-lookup pages, restyled.

`POST /checkout` and `POST /payment/callback` keep their current paths.

### Livewire islands

Browsable pages are plain controllers and Blade — cheap, cacheable, and
indexable. Livewire is reserved for genuinely stateful parts:

- **ProductPurchase** — option selection resolving to a variant; personalization
  inputs driven by each product's `personalization_options` (`max_characters`,
  `allowed_pattern`, `is_required`); live price including `price_delta_minor`;
  add to cart.
- **Cart** — lines, quantity, remove, subtotal.
- **CheckoutForm** — customer details; a country change re-quotes through
  `ShippingCalculator`; discount codes applied through `DiscountService`.
- **CartCount** — a small header component listening for a `cart-updated` event.

### One write path

The orchestration currently inside `CheckoutController::store()` is extracted
into a `PlaceOrder` service. The existing `POST /checkout` route keeps calling
it, so Plan 1's checkout tests stay meaningful, and the Livewire form calls the
same service. Two entry points, one implementation — the pattern
`TransitionActions` already established for admin status changes.

This is the only refactor of tested code in this plan.

### Made to order

`lead_time_days` already exists on every product, so product and cart pages read
"made to order — ships in N days" rather than implying instant dispatch.

## 4. Error handling and edge cases

- **Capacity exhausted** (`stock_quantity` at zero): the product reads
  "currently unavailable" and add-to-cart refuses, rather than accepting an
  order that cannot be made.
- **Capacity lost between cart and checkout:** `createFromCart()` throws
  `InsufficientStockException`, which the existing controller already catches;
  checkout names the offending item and preserves the cart.
- **Invalid or expired discount:** `InvalidDiscountException` renders as an
  inline field error, not a page failure.
- **No shipping rate for the destination:** `NoShippingRateException` gets a
  clear message. This is the failure an operator can cause from the admin, which
  is why the fallback-zone guard and bracket warning exist.
- **Product deactivated while in a cart:** the line is dropped with a visible
  notice rather than failing at checkout. `CartSnapshot`'s current behaviour here
  must be confirmed during implementation and corrected if it silently retains
  the line.
- **Missing translation:** falls back to the default locale.
- **Missing image:** the placeholder frame holds the aspect ratio, so the layout
  never collapses.
- **Empty catalogue and empty cart:** designed empty states, not blank pages.

## 5. Testing

- Feature tests per route, including locale prefixing and fallback.
- Livewire component tests (the Pest Livewire plugin is installed) for variant
  selection, personalization validation, cart mutation, shipping re-quoting and
  discount application.
- A translation test proving a product renders in Azerbaijani and falls back
  cleanly when a field is empty.
- A snapshot test proving an order placed in Azerbaijani freezes Azerbaijani
  text into `order_items` with `orders.locale` set.
- **Plan 1's existing checkout tests must pass untouched.** They are the
  regression guard on the `PlaceOrder` extraction.

The suite is currently 175 passed / 2 skipped on SQLite, and 177 passed / 0
skipped on MySQL. Both concurrency tests skip on SQLite by design.

## Items to verify during implementation

These are known unknowns, called out so they are not discovered late:

1. Whether the chosen serif ships `ə` (U+0259) and the rest of the Azerbaijani
   set. Blocking for the type choice.
2. Whether `CartSnapshot` currently drops or retains a line whose variant has
   become inactive.
3. Whether `CartService::snapshot()` applies `price_delta_minor` to the line
   price, or whether the storefront must surface it separately.
