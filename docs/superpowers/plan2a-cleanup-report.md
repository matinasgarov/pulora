# Plan 2A cleanup — deferred review findings

Branch: `plan2a-cleanup`, forked from `master` @ 3e7ba15 (Plan 2A merged).
Baseline: 163 passed / 1 skipped. Final: **175 passed / 1 skipped** (+12 tests).

The nine findings below were triaged as non-blocking during Plan 2A's per-task
and final reviews. This pass clears them. The 1 skip is the pre-existing
`OversellTest` (needs real MySQL) and remains out of scope.

## 1. Unguarded deletes on DiscountCode and ShippingZone

The final review's fix wave guarded Product/Variant deletes but left these as an
explicit out-of-scope observation.

- `DiscountCodeResource::canDelete()` — a code with `times_used > 0` is no longer
  deletable. Deleting one destroys the record of why past orders were discounted;
  `is_active = false` is the retirement mechanism, matching the product philosophy.
- `ShippingZoneResource::canDelete()` — the *last remaining* fallback zone is no
  longer deletable. Deleting it leaves Plan 1's `ShippingCalculator` returning zero
  quotes for unlisted countries, silently breaking checkout — the same failure the
  `is_fallback` toggle's existing `rule()` already guards.
- Both wired via `DeleteAction::make()->authorize(...)` on the Edit pages, matching
  the shape the fix wave established for `EditProduct`.

Rate-bracket deletion is deliberately left unguarded — a hard guard there is
over-engineering, and the existing gap warning in `RatesRelationManager` is the
agreed approach.

## 2. `TransitionActions` — nullability trap

`deliver()`, `cancel()`, and `refund()` still type-hinted non-nullable `Order` in
their `visible()` closures while the other three had been widened to `?Order` for
the Workshop page's pre-mount header render. Correct today (Workshop registers only
the widened three) but a live trap: adding `cancel()` to Workshop would have
fataled. All six now use the same `?Order` + `?->` + `?? false` pattern.

## 3. Discount `kind` switch reinterpreting a stored value

Switching an existing code from `percent` to `fixed` silently reinterpreted its
stored `value` — a "10% off" code became "10.00 AZN off" (1000 minor) while orders
referencing it already existed. The `kind` field is now disabled once
`times_used > 0`, with helper text, mirroring the slug lock on an ordered product.

## 4. `minValue(1)` was a no-op on the money branch

**Judgment call.** On the `fixed` branch the field holds a money *string*
("10.00"), so Laravel's `min` rule measured string length, not value. A blanket
`->numeric()` would have broken both the money string and the existing
`regex:/^\d+([.,]\d{1,2})?$/` rule, so the minimum is enforced per branch instead:
`percent` keeps the plain `minValue(1)`, and `fixed` gets a closure rule that
converts through `MoneyInput::toMinor()` and fails below 1 qəpik. Pinned by two
tests — one rejecting a below-minimum amount, one accepting `0.01` and asserting it
stores as `1`.

## 5. Redundant `homeUrl`

Dropped `->homeUrl('/admin')` from `AdminPanelProvider`; the Workshop page's
`getRoutePath()` override already lands `/admin` on the Workshop. `PanelAccessTest`
and `WorkshopTest` both still pass.

## 6. Misleading failed-login log label

**Judgment call.** The `Failed` listener fires for *any* `Auth::attempt()` in the
app but hardcoded the message `'Failed admin login'`. Rather than scoping the
listener (which would silently stop logging a second auth mechanism — the wrong
default for a security log), the message is now `'Failed login attempt'` and the
event's actual `guard` is logged as a field. The listener keeps its full coverage
and the record stops claiming more than it observed.

## 7. Pint pass

Ran over the Plan 2A files only. Two notes:

- Pint's Laravel preset rewrote the repo-wide `<?php // path/to/file.php` header
  convention into three lines across 8 files. That convention is deliberate and
  used throughout the codebase, so **it was reverted** — the header style is
  unchanged from master.
- Pint also normalized union catches to `A|B` (no spaces) and concatenation
  spacing. `TransitionActions` is the only file in the repo with union catches, so
  this introduced no inconsistency with untouched code.

## 8. Test coverage gaps

- **Multi-key personalization.** The review flagged `ViewOrder`'s
  `formatStateUsing` array branch as untested. Verified empirically (throwaway
  test, since deleted): Filament *flattens* array state and invokes the closure
  once per value, so the keys never render and `is_array($state)` is never true for
  this data shape — the `"{$key}: {$value}"` branch is unreachable through the UI.
  The first attempt at this test asserted `'monogram: MA'` and failed for exactly
  that reason. The test now pins the real, user-visible behavior: every value of a
  multi-key personalization is shown, so a second option cannot go unnoticed on the
  workshop floor. The defensive array branch is left in place as cheap insurance
  against a nested-value shape, but it is documented here as currently dead.
- **Workshop actions.** `mark_ready` and `ship` are now exercised through the
  Workshop card path, alongside the pre-existing `start_production` coverage.

## 9. `deploy.md`

Added `php artisan view:cache` and `php artisan filament:optimize` to the "On the
server" steps. The `composer install` step, corrected cron prose, and
`QUEUE_CONNECTION`/`PAYMENT_DRIVER` notes from the previous fix wave are untouched.

## Deliberately not changed

Reviewed and judged correct as-is:

- `MoneyInput`'s 2-decimal UI regex making the half-up-on-third-decimal path
  unreachable from the panel — matches the spec, covered by unit test.
- `DiscountCodeResource`'s `value` field concentrating kind-branched behaviors
  (findings 3 and 4 were made as targeted changes without restructuring it).
- `Workshop`'s `$resolveRecord` + `->after()` duplication across three registrations.
- `PaymentLogResource`'s `KeyValueEntry` rendering nested payloads as `Array` —
  current gateway payloads are flat.
- `OversellTest` / MySQL — out of scope.

## Concerns

- `OrderResourceTest` uses `assertHasActionErrors()`, which the IDE reports as
  deprecated in favour of `assertHasFormErrors()`. Pre-existing, not touched here;
  worth a sweep if the Filament/Pest stack moves again.
- The `personalization` array branch (finding 8) is dead code for every shape the
  app currently produces. Left in deliberately, but it is a candidate for deletion
  if a future reader would rather not maintain an unreachable path.
