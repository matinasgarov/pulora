# SDD ledger — plan: docs/superpowers/plans/2026-08-14-storefront.md

Spec: docs/superpowers/specs/2026-08-14-storefront-design.md (read; binding authority)
Worktree: C:\Users\Matin Asgarov\leather-shop\.claude\worktrees\storefront
Branch: worktree-storefront (from master @ acf294c)
Baseline: 175 passed / 2 skipped on SQLite; 177 / 0 on MySQL.

## Pre-flight scan

### Shared-file / shared-interface pairs

| Tasks | Produces → Consumes | Finding |
|---|---|---|
| 1 → 2 | `getTranslations()` / `setTranslation()` → admin mutate hooks | Clean. T2 uses only methods T1 defines. |
| 1 → 5,6 | `$product->name` resolves to string → views print it | Clean. T1's `getAttributeValue` override is what makes T5/T6 views work unchanged. |
| 1 → 8,9 | `orders.locale` column → PlaceOrder writes it, mails read it | Clean. Column created T1, written T8, consumed T9. |
| 3 → 4 | `storefront.*` route names + `shop.*` lang keys → header/footer | Clean. T4 test asserts `shop.nav.cart` values that T3's lang files define ('Cart'/'Səbət'). |
| 3 → 5 | catalogue closure → CatalogueController | **Conflict (resolved below): route file edited by 3, 4, 5, 6, 7, 8.** |
| 4 → 5 | `<x-layouts.storefront>`, `<x-price>` → catalogue view | Clean. |
| 4 → 7 | header cart-count placeholder comment → `<livewire:cart-count />` | **Ordering hazard (resolved below).** |
| 5 → 6 | product tile links `storefront.product` → ProductController | Clean; route name defined T3. |
| 6 → 7 | `cart-updated` event → CartCount `#[On('cart-updated')]` | Clean; event name identical in both. |
| 6 → 7 | unit price = effective + delta → cart line total | Clean, and deliberately mirrored; plan states snapshot() already folds the delta. |
| 7 → 8 | cart page "Checkout" link → `storefront.checkout` route | Clean. |
| 8 → 9 | `PlaceOrder` writes `orders.locale` → mails localise from it | Clean. |
| 1 → all | plain-string tolerance | Clean; T1 Step 1 pins it with an explicit test. |

### Per-task internal consistency

| Task | Finding |
|---|---|
| 1 | Tests match trait API. Migration widens the same six columns it wraps. Consistent. |
| 2 | Test fills `name_en`; form defines `name_en`. Consistent. Note it mandates editing Plan 2A's `ProductResourceTest`. |
| 3 | Test asserts `/de` 404s; middleware aborts 404 on unsupported. Consistent. |
| 4 | Test expects brand name + `/az` link + `lang="az"`; header/layout provide all three. Consistent. |
| 5 | Test expects empty state and tiles; view branches on `isEmpty()`. Consistent. |
| 6 | Tests assert rendered prices via `assertSee(Money::format(...))` rather than `assertSet` on a computed property — correct, since `unitPriceMinor` is a computed property and cannot be asserted with `assertSet`. Consistent. |
| 7 | Test drives `remove($lineKey)`; component signature is `remove(CartService $cart, string $lineKey)` — Livewire injects the service, so the call passes only the key. Consistent. |
| 8 | `PlaceOrder` body is "moved verbatim" rather than written out. See ruling below. |
| 9 | Consistent. |

### Rulings

**Ruling: routes/web.php is edited by six tasks; that is intended, not a conflict.**
Each task replaces exactly one placeholder closure with its controller, and the
tasks run strictly in sequence, so there is no concurrent edit. Cost if wrong:
a merge conflict inside one task, resolved in that task.

**Ruling: Task 4 leaves the header's cart-count as a comment; Task 7 replaces it.**
The plan states this explicitly in both tasks. The alternative — T4 referencing a
component that does not exist yet — would break every T4 test. Cost if wrong:
a reviewer flags an apparently dead comment in T4; the T7 diff resolves it.

**Ruling: Task 8's `PlaceOrder` body is specified as a verbatim move, not written out.**
This is the correct instruction for an extraction refactor: writing fresh code
would risk changing behaviour that Plan 1's tests currently guarantee. The guard
is stated in the task — Plan 1's checkout tests must pass **unedited**. Cost if
wrong: the extraction drifts and those tests fail, which is exactly the signal
the task tells the implementer to treat as "revert and redo".

**Ruling: Task 2 will require editing Plan 2A's `ProductResourceTest`.**
That test fills a `name` field which becomes `name_en`. The plan names this a
legitimate consequence rather than a regression. Cost if wrong: an edited test
masks a real break; mitigated because the edit is mechanical (field rename only)
and the reviewer sees the diff.

## Tasks

**Task 1: complete** (c466b32 implement, d14c9f1 review fixes) — 196 passed / 2 skipped.

`HasTranslations` + six models + reversible JSON migration + `orders.locale`.
Implementer reported 188/2 with no concerns; the review returned FIX-REQUIRED on
two findings, both of which I confirmed empirically before acting:

1. **"Tolerates plain strings" was false at the model layer.** Laravel's `array`
   cast `json_decode()`s the column, so genuinely raw text returned null and
   resolved to `''` — content lost silently. The existing tests could not catch
   it: they built their "plain string" through Eloquent, which JSON-encodes on
   the way in, so the value had already survived a round trip before being read.
   Fixed by falling back to the raw attribute in both read paths.
2. **`unwrap()` emptied a value of `"0"`.** `$d[$fb] ?? reset($d) ?: ''` — I
   checked the `??`/`?:` precedence expecting the reviewer to be wrong; they were
   right. Now tests emptiness explicitly.
3. **`down()` had zero committed coverage** — verified once by hand against a
   throwaway DB. `TranslatableMigrationTest` now drives the migration object in
   both directions. All three new assertions confirmed to fail against the
   unfixed code.

Reviewer's Minor findings (non-string values in the any-locale loop; the
redundant `WIDEN`/`TARGETS` split) accepted as-is, not defects.

**Task 2: complete** (d053d71 implement, 5effee3 + a73fde2 fixes) — 205 passed / 2 skipped.

Per-locale product and variant content entry. Review returned ACCEPT with no
Critical or Important findings, but two things still needed doing:

1. **The `name_en` rename was left half-done.** Two `fillForm(['name' => …])`
   calls remained on a field the form no longer has. Both tests passed — Filament
   ignores an unknown key — which is precisely the problem: the price-drift test
   had quietly stopped filling any name at all.
2. **The reviewer's own Minor turned out to be a data-loss bug.** They noted the
   variant round trip was verified by a throwaway probe and never committed. I
   rebuilt the probe to keep it, and it failed: the relation manager read the
   per-locale values out of the form-data array rather than through the record,
   so a plain-string description opened the form blank and saving destroyed it.
   Now goes through `getTranslations()` like `EditProduct` does. Both
   plain-string assertions confirmed to fail against the unfixed hook.

Ruling upheld: the action-level hooks (`mutateFormDataUsing` /
`mutateRecordDataUsing`) are the correct adaptation — relation managers genuinely
have no `mutateFormDataBeforeFill/Save/Create`. The deviation from the brief was
necessary; only the data source inside it was wrong.

**Task 3: complete** (d11fdbd implement, 91e0388 review fix) — 218 passed / 2 skipped.

Locale routing, `SetLocale`, `shop.*` lang files. Review returned FIX-REQUIRED on
one finding, which was correct and which my own brief had also missed:

- **`GET /admin` is a one-segment route with the same shape as `/{locale}`.**
  I added four collision tests to the brief but pinned `/admin/login` — two
  segments, defined in `web.php`, never a candidate. The real landmine was bare
  `/admin` (Filament's Workshop). It resolved correctly only because panel
  providers register at index 1 and the group at index 37; nothing enforced that.
  Now constrained with `whereIn`, so ordering cannot matter. Verified under
  `route:cache` too, since production caches routes.

Deviations accepted: `CheckoutConfirmationController` gained a
redirect-when-no-order guard. This entered through a bad assertion in *my* brief
(`assertRedirect` on a cold visit, which the controller did not then do), but the
change is right on its own merits — the old path rendered "Your order  is
confirmed" to a stranger. Plan 1's `CheckoutFlowTest` still passes unedited.
`ExampleTest.php` deleted: stock scaffold asserting `GET /` returns 200, now
false by design.

Reviewer confirmed `URL::defaults()` does not leak — Laravel injects a default
only for routes that declare the parameter, and no route outside `storefront.*`
declares `{locale}`. No signed routes exist. Suite is order-independent under
`--order-by=random`.

**Task 4: complete** (a15ed27 implement, e3271eb + 48d5e7a fixes) — 233 passed / 2 skipped.

Design system, layout shell, `<x-price>`. Brand is **Pulora** — the user corrected
this mid-task; the spec and plan still say "Leather Shop" and are superseded.

Caught before dispatch, by checking the font myself: the plan's instruction to
import `latin-ext-400.css` would have broken the type. Fontsource splits `latin`
(ASCII + ö ü ç ı) from `latin-ext` (ə ğ ş İ), and the per-subset files carry no
`unicode-range` — so the browser would have loaded a woff2 with no ASCII in it
and fallen back to Georgia for every ordinary letter. Aggregate `400.css`/`500.css`
instead. The implementer turned this into `FontSubsetsTest`, which parses the
*built* CSS and proves distinct subsets cover U+0041 and U+0259.

Caught by rendering the page and looking at it — neither would ever fail a test:
- the announcement bar was `bg-ground`, identical to the body, so it had no edge
  and read as stray text rather than a bar;
- the footer said "Baku" in both locales while the bar above it said "Bakıda".

Caught by the review, in my own fix: moving the bar to `bg-tile` dropped muted
text to **4.509:1**, nine thousandths above the AA floor. The brief told the
implementer not to lighten `--color-muted`; darkening the background under it
does the same thing, and I did it without rechecking. Token darkened to `#5f5e5a`
(5.87 ground / 5.41 tile) and `ContrastTest` now computes every pairing from the
token definitions at a **5.0:1 house floor** — deliberately above WCAG's 4.5,
because a threshold the palette only just clears is not a guard.

Reviewer also confirmed: `<x-price>` is the sole money path; dropping
`@livewireStyles`/`@livewireScripts` is correct for Livewire 4.3.5 and would have
double-loaded once Task 7 adds a component; `:focus-visible` is not overridden.

Note the pattern across the first two tasks: an agent verifies something by hand, deletes
the evidence, and reports no concerns. Both times the deleted probe was hiding a
real defect. Committed coverage is the deliverable, not the verification.

---

## Outcome

Merged to `master` as a fast-forward at `fadbf7b` and pushed to
`github.com/matinasgarov/pulora`. Final suite: **276 passed / 2 skipped**
(baseline at the start of Plan 2B was 175 / 2). The 2 skips are the MySQL-only
concurrency tests and are correct on SQLite.

The brand is **Pulora**. The spec and plan documents in `docs/superpowers/`
predate that decision and still say "Leather Shop" in their markup snippets;
they are historical records of what was approved at the time and were not
rewritten. `plan2b-plan-corrections.md` is the file that superseded them during
execution.

### Deferred to Plan 2C

- Editorial home page (2B ships the catalogue as the landing page)
- About / craft page
- Presentation of each product's `story` field (the column and its per-locale
  admin fields exist; nothing renders it yet)

### Known, deliberately not fixed

- **Narrow-viewport rendering is unverified.** Headless Chrome on this machine
  clamps the window to ~484px, so every `--window-size=390` screenshot was a
  390px crop of a 484px layout rather than a 390px render. The header's
  small-screen treatment is precautionary. Check on a real device or with CDP
  device emulation before trusting it.
- **The cart has no quantity stepper** — remove-only. This matches Task 7's plan
  section and tests, but the spec's component list mentions one.
- **The checkout total does not preview a discount** before submit; the discount
  is applied correctly server-side at order creation. Display-only gap.
- **A tampered `variantId`** could add an inactive variant to the cart, which is
  then silently dropped at `snapshot()`. Not reachable through the rendered UI —
  it needs a hand-built Livewire payload.
