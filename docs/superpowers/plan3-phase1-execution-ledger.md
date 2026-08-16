# Pulora design Phase 1 — execution ledger

Plan: docs/superpowers/plans/2026-08-16-pulora-design-phase1.md
Worktree: .claude/worktrees/design, branch worktree-design, from master @ fadbf7b

## Tasks (all complete, no per-task review gate — one final review instead)

1. Tokens/type/locale — 01da4eb. Bodoni Moda + Archivo self-hosted (aggregate
   stylesheets, both verified to carry the full Azerbaijani set). Default
   locale flipped en→az. Plan's own contrast table had an arithmetic error
   (claimed 4.9:1, actual 4.55:1 for muted-light) — corrected in the plan
   rather than silently accepted; see "Which floor applies to what" in the
   plan for the resulting two-floor ContrastTest design.
2. Header/drawer/search/footer — c76e559, fix 1b5e068. Checkbox-driven
   drawer/search (no Alpine, works with JS off). Real cart badge via
   <livewire:cart-count/>. Footer contact column initially repeated the
   brand block's address/hours instead of giving real contact channels —
   fixed to Instagram/WhatsApp/email.
3. Product design fields — b8e3fe7. leather/category/tag/specs added to
   products. specs is a translatable LIST, not a string, so it got its own
   TranslatableListCast rather than bending HasTranslations (which is typed
   to return string). tag is operator-set, not derived from stock_quantity.
4. Homepage — 82c5ba6. Hero/collection/bespoke/atelier. Quick-add only fires
   with exactly one active variant and no required personalization; all 3
   demo products have multiple variants so all link to the product page.
   Phase-2 controls (tabs/filter/sort) genuinely disabled, not dead-looking-live.
5. Product page — 0194c8b. Restyle only — ProductPurchase still owns variant
   selection, personalization, pricing. Swatch colours via exact-match
   name→hex lookup with a labelled-button fallback for unrecognized names.
6. Cart/checkout/confirmation/lookup — 1aa2777. Restyle only, zero PHP
   touched. CheckoutFlowTest and OrderLookupTest confirmed byte-identical.

## Final review

FIX-REQUIRED verdict on one Critical (mobile header overflow) and one
Important (drawer trigger a11y). Both investigated before acting:

**Critical did not reproduce.** The reviewer's screenshots used
`--window-size` + `--screenshot`, which silently floors around ~484px on
this machine — the exact artifact this project already burned time on once
in Plan 2B (documented in plan2b-execution-ledger.md). Re-measured with
Playwright's real viewport emulation at 320/375/390/414/430px:
document.scrollWidth ≤ viewport at every width except a 1px rounding
artifact at 320px. Screenshotted the header alone at 320 and 390 — wordmark,
bag icon and hamburger all render correctly, nothing clipped. No fix applied.

**Important accepted as a known gap, not fixed.** Drawer/search triggers are
`<label for="checkbox-id">`, not `<button aria-expanded>` — a deliberate
choice so the drawer works with JavaScript off (an explicit Task 2
requirement). A screen reader announces the checkbox's checked state, just
not with aria-expanded semantics. Fixing this properly means progressive
enhancement (real button + JS, checkbox as no-JS fallback), which is a
real but non-trivial change — logged for a follow-up pass rather than
risking a JS-dependency regression under time pressure.

Everything else the review checked came back clean: CheckoutFlowTest and
OrderLookupTest unedited, live-cart flag correct on exactly the right pages,
specs cast correct, quick-add eligibility correct at all boundary cases,
swatch colours exact-match (no substring mis-map risk), no USD/`$` leftover
anywhere, no direct Money::format() outside x-price, no dead href="#".

Full suite: 342 passed / 2 skipped throughout, unchanged by the review pass.

## What Phase 1 does NOT cover (by design — see the plan)

Search/filter/sort/wishlist wiring (Phase 2), bespoke configurator + inquiry
backend (Phase 3), real photography, real product copy beyond the demo seed.
