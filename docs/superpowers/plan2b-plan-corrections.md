# Corrections to the plan — read before implementing any remaining task

The plan at `docs/superpowers/plans/2026-08-14-storefront.md` is your instruction
set. Follow your task's section step by step. But the plan was written before
Tasks 1–4 ran, and the following supersede it. **Where the plan and this file
disagree, this file wins.**

## 1. The brand is Pulora, not "Leather Shop"

The plan's snippets say "Leather Shop" in the wordmark, `<title>` and footer.
That is superseded. Every user-visible brand string is **Pulora**. The layout and
header already say Pulora — do not change them back, and do not introduce
"Leather Shop" anywhere new.

## 2. Money formatting

`Money::format()` lives at `App\Domain\Money` (the spec text says
`App\Support\Money`; that class does not exist).

**Every price in the storefront goes through `<x-price :minor="…" />`.** No view
formats money itself. This is a hard constraint, not a preference.

## 3. Do not touch these

- The `whereIn('locale', SetLocale::SUPPORTED)` constraint on the route group in
  `routes/web.php`. It stops `/{locale}` from swallowing `GET /admin`. Removing
  it takes down the admin panel.
- The `@import '@fontsource/eb-garamond/400.css'` / `500.css` lines in
  `resources/css/app.css`. Importing the per-subset `latin-ext-*.css` files
  instead renders every ASCII letter in a fallback face. `FontSubsetsTest` will
  go red if you change this.
- `--color-muted: #5f5e5a`. `ContrastTest` asserts a 5.0:1 house minimum on every
  colour pairing. Lightening any foreground, or darkening any background under
  existing text, will go red.
- `@livewireStyles` / `@livewireScripts` are deliberately ABSENT from
  `resources/views/components/layouts/storefront.blade.php`. Livewire 4
  auto-injects once a component renders. Do not add them back — you would
  double-load Livewire's assets.

## 4. Route helpers in storefront views

Use `route('name', absolute: false)` for internal storefront links, matching the
header and footer. The `{locale}` parameter is supplied automatically by
`URL::defaults()` in `SetLocale`, so you do not pass it.

## 5. Facts already established by reading the code — do not re-derive

- `CartService::snapshot()` **already folds** `price_delta_minor` into
  `CartLine::$unitPriceMinor`. Do not add it again; you would double-charge
  personalization.
- `CartService::snapshot()` **silently drops** lines whose variant or product has
  become inactive (a bare `continue`). The spec (§4) requires the customer be
  *told*. Task 7 owns making that visible via `shop.cart.line_removed`.
- `CartService::add()` **already validates** personalization against the
  product's own options. Do not re-implement those rules in the UI.

## 6. Baseline

The suite is at **233 passed / 2 skipped** after Task 4. The 2 skips are the
MySQL-only concurrency tests and are correct on SQLite. Your task's test count
adds to that. Never edit an existing test to make your work pass — if one breaks,
the fix is in your implementation.

## 7. Global Constraints (binding, from the spec)

- Money is an integer in minor units (qəpik); conversion is string-parsed, never
  `(int) ($value * 100)`.
- Currency is AZN only.
- `order_items` are immutable snapshots; never joined live to the catalogue.
- `variants.stock_quantity` is an operator-set capacity cap, not shelf inventory.
- Guest checkout only; the `users` table holds operators.
- No code path writes `orders.status` directly — every change goes through
  `OrderService::transition()`.
- **The storefront never re-implements a domain decision.** Prices, shipping
  quotes, discount validity and personalization rules are resolved server-side by
  the existing services. The UI renders their answers.
- One write path per operation.
- File header convention: PHP files under `app/` start with a single line
  `<?php // path/to/file.php`. Blade files take no header.

## 8. Reporting

Write your report to `.superpowers/sdd/2026-08-14-storefront/task-N-report.md`.
Cover what you did, any deviation and why, test counts, the commit SHA, and real
concerns.

If you verify something by hand and it proves something worth knowing, **turn it
into a committed test** rather than deleting it and reporting that you checked.
Four times on this plan a deleted hand-verification was sitting on top of a real
bug.

End your final message with `DONE, commit <sha>, <counts>` or `BLOCKED: <reason>`.
