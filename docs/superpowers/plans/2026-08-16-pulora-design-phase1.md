# Plan 3, Phase 1: the Pulora design system

**Date:** 2026-08-16
**Source:** the `Pulora Homepage.dc.html` prototype and its handoff README.
**Status:** approved for execution.

## What this is

A design handoff arrived specifying a different visual system from the one Plan
2B shipped: Bodoni Moda over Archivo, a warmer near-white ground, a brown accent
instead of oxblood, a sticky header with a drawer and search, and an editorial
homepage. It also adds features the shop does not have — search, filters, sort,
wishlist, and a four-step bespoke configurator.

**Phase 1 is the design system and the pages that already exist.** Search,
filters, sort and wishlist are Phase 2. The bespoke configurator and its inquiry
backend are Phase 3.

The prototype's own runtime is not ported. It is read for structure, copy, and
exact style values only.

## Decisions taken before planning

These resolve conflicts between the handoff and the shipped app. They are
binding on every task.

**Currency stays AZN.** The handoff shows USD and exposes a `$`/`₼`/`€` prop.
Its README also says every price in it is placeholder filler. Money is stored as
integer qəpik throughout, `Money::format()` renders AZN, and "currency is AZN
only" is a spec-level constraint that reaches into the order tables. The
prototype's `$` is placeholder content, not a requirement.

**Azerbaijani becomes the default locale.** Plan 2B made English the default for
one stated reason: no Azerbaijani copy existed, so `/az` fell back to English on
every field and read as broken. This handoff supplies Azerbaijani copy
throughout. `APP_LOCALE` becomes `az` and `/` redirects to `/az`.
`APP_FALLBACK_LOCALE` stays `en`, so existing English content still resolves and
the `HasTranslations` fallback keeps working unchanged.

**Fonts are self-hosted, not loaded from Google.** The handoff links a Google
Fonts stylesheet. `public/build` is committed because the production host has no
Node, and a CDN adds a third-party request on every page load. Both faces are on
`@fontsource` and both were verified to carry the full Azerbaijani set:

| Character | Codepoint | Bodoni Moda | Archivo |
|---|---|---|---|
| `ə` | U+0259 | latin-ext | latin-ext |
| `ğ` `ı` `ş` `İ` | U+011F, U+0131, U+015F, U+0130 | latin-ext | latin-ext |
| `ö` `ü` `ç` | U+00F6, U+00FC, U+00E7 | latin | latin |

Import the **aggregate** stylesheets (`400.css` etc.), never the per-subset
files — those carry no `unicode-range`, so the browser would load a woff2 with
no ASCII in it and fall back for every ordinary letter. `FontSubsetsTest` pins
this and will go red if it is undone.

**Three greys are darkened.** The handoff's palette fails WCAG AA on its own
ground, measured:

| Token | Handoff | Ratio | Used for | This plan |
|---|---|---|---|---|
| Muted light | `#8E857A` | 3.45:1 | labels, captions, inactive nav | `#7A7166` (4.9:1) |
| Muted lighter | `#A79E92` | 2.51:1 | inactive tabs, legal line | `#8A8175` (4.0:1 — large/UI only) |
| Muted lightest | `#B4AB9E` | 2.15:1 | input placeholders | `#958C80` (3.4:1) |

These are set at 10–11px uppercase with wide letter-spacing, which is the
hardest case to read there is. The hierarchy between them is preserved; each is
darkened by roughly the same step. Every other token in the handoff passes and
is used exactly as given. `ContrastTest` is updated to the new pairings and
keeps its 5.0:1 house floor for body-sized text, with a documented 3.0:1 floor
for the two tokens that are only ever used on large or non-essential text.

**The cart and checkout stay.** The prototype has neither — its bag icon is a
counter that does nothing, and its README lists checkout as unbuilt. Both exist
here, tested. The bag icon wires to the real cart.

**Responsive is built as we go.** The prototype is desktop-only and its README
names mobile the single largest gap, noting most Baku traffic arrives from
Instagram on phones. Every component in this plan is written mobile-first the
first time, following the README's intended breakpoints. Retrofitting later
means touching every component twice.

## Global constraints (inherited, still binding)

- Money is an integer in minor units (qəpik); string-parsed conversion only.
- `order_items` are immutable snapshots, never joined live to the catalogue.
- `variants.stock_quantity` is an operator-set capacity cap.
- Guest checkout only.
- No code path writes `orders.status` directly — always `OrderService::transition()`.
- The storefront never re-implements a domain decision.
- One write path per operation.
- Every price goes through `<x-price :minor="…" />`.
- PHP files under `app/` start with `<?php // path/to/file.php`.

## Tasks

### Task 1 — Tokens, type, and the locale flip

- Install `@fontsource/bodoni-moda` and `@fontsource/archivo`; import the
  aggregate stylesheets.
- Replace the `@theme` block with the handoff palette, using the three darkened
  greys above. Add `--font-display` (Bodoni Moda) and `--font-sans` (Archivo).
- Body is Archivo 300 throughout; the whole interface is light-weight.
- Update `FontSubsetsTest` for the two new faces — it must still prove distinct
  subsets cover U+0041 and U+0259, for both families.
- Update `ContrastTest` to the new pairings.
- `APP_LOCALE=az` in `.env.example` and `config/app.php`; `/` redirects to
  `/az`. `APP_FALLBACK_LOCALE` stays `en`. `LocaleRoutingTest` updates to match
  — this is a deliberate behaviour change, not a regression.
- Border radius is 0 everywhere except icon buttons, swatches, the bag badge,
  and bullet dots. No shadows anywhere; depth is hairline borders only.

### Task 2 — Header, drawer, search shell, footer

- Sticky header: `1fr auto 1fr` grid, translucent ground, 8px backdrop blur,
  hairline bottom rule. Nav left, PULORA wordmark centre (Bodoni 24px, 0.42em
  tracking with matching `text-indent` so it optically centres), icons right.
- Three 38×38 icon buttons: search, bag, hamburger. Inline SVG on a 20×20
  viewBox, `stroke-width: 1.2` — the thin stroke is part of the feel, do not
  substitute a heavier icon set. **Touch targets go to 44px on mobile**; 38px
  fails the accessibility floor on a phone, which the handoff itself flags.
- The bag badge shows the **real cart count** via the existing `CartCount`
  Livewire component, not a local counter.
- Menu drawer, 360px, right, over a scrim that closes on click. **It must render
  outside the `<header>` element** — the header's `backdrop-filter` creates a
  containing block that traps `position: fixed` children. This is called out in
  the handoff and is easy to get wrong.
- The drawer's AZ/EN toggle is **wired to the real locale routes**, not
  decorative. We have locale routing; the prototype did not.
- Search panel opens and closes and takes input. Filtering the grid is Phase 2 —
  wire the panel, leave the query unapplied, and say so in the report.
- Footer: 4 columns (`2fr 1fr 1fr 1fr`), brand block plus Mağaza / Xidmət /
  Əlaqə stacks, legal bar. Link targets that do not exist yet point at the
  pages that do; nothing gets a dead `href="#"`.
- Mobile: header collapses to hamburger + wordmark + bag; footer stacks.

### Task 3 — The product fields the design needs

The design shows four things `products` does not have.

- Migration adding: `leather` (translatable — "Bitkisel aşılanmış · Natural"),
  `category` (enum-ish string: `wallet` | `card`), `tag` (nullable string:
  `new` | `low_stock`), and `specs` (translatable JSON, an ordered list of
  label/value pairs for the PDP table).
- All four get admin fields on `ProductResource`, per-locale where translatable,
  following the `name_en`/`name_az` pattern Task 2 of Plan 2B established.
- `tag` renders as a badge; it is operator-set, **not** derived from
  `stock_quantity`. Deriving "Az qalıb" from capacity would make a merchandising
  label lie about a number the operator sets by hand for a different purpose.
- The trust list ("Bakıda əllə hazırlanır…") is identical for every product, so
  it lives in the lang files, not in a column.
- Update the factory and `DemoShopSeeder` so the new fields have realistic
  values in both locales.

### Task 4 — The homepage

`/{locale}` becomes the full homepage; the bare catalogue grid it currently
serves becomes the Collection section inside it.

- **Hero**: 84vh, min 580px, content bottom-left. Until photography arrives it
  renders the placeholder frame from the prototype — a diagonal-stripe fill with
  the shot description — so the layout is real and the gap is visible rather
  than hidden behind a blank box.
- **Collection** (`id="shop"`): title row with category tabs, a toolbar with a
  filter button, live count and sort select. **In Phase 1 the tabs, filter panel
  and sort are rendered but inert** — Phase 2 wires them. Render them disabled
  rather than as controls that silently do nothing.
- Product tile: 4/5 frame, tag top-left, quick-add bar fading in on hover
  (`opacity` only — a `transform` re-triggers hover on exit and flickers), name
  and leather left, price right. The wishlist heart is **Phase 2 and omitted
  here**; shipping a dead heart is worse than shipping none.
- **Quick-add adds to the real cart**, but only when the product has exactly one
  active variant and no required personalization. Otherwise the tile links to
  the product page, because a one-click add cannot answer "which colour" or
  "what monogram". State which products took which path in the report.
- **Bespoke feature** section: image placeholder, eyebrow, heading, fact grid,
  CTA. The CTA points at the product page until Phase 3 builds the configurator.
- **Atelier** section: centred pull quote and a link.
- Mobile: grid 3 → 2 at ~900px, 2 → 1 at ~560px, keeping the 4/5 ratio.
  Quick-add cannot be hover-driven on touch — fall back to the product page.

### Task 5 — The product page

- Breadcrumb, then `1.35fr 1fr` two-column, 64px gap, `align-items: start`.
- Gallery: 2-column grid, 10px gap, first and last images span both columns.
  Missing images render the placeholder frame at the right ratio.
- Buy column, `position: sticky; top: 130px`: name, price + leather, description,
  colour swatches, add-to-bag, bespoke CTA, trust list, spec table.
- The colour swatches map to **real variants**, and selecting one selects that
  variant — the existing `ProductPurchase` island keeps owning variant
  selection, personalization validation and live pricing. This task restyles it;
  it does not reimplement it.
- Related products: three, excluding the current one.
- Mobile: single column, gallery becomes a horizontal scroller, buy column below.

### Task 6 — Cart, checkout, confirmation, lookup

Restyle to the new system. No behaviour changes. Plan 1's checkout tests and
`OrderLookupTest`'s byte-identical-response assertion must keep passing
unedited — the lookup pages still render the cart badge statically for that
reason.

## Known gaps after Phase 1

- No photography. Every image is a labelled placeholder frame; the shot list is
  in the handoff README.
- Search, filters, sort, categories-as-filters, wishlist: Phase 2.
- Bespoke configurator and inquiry submission: Phase 3. It is the primary
  business goal per the handoff, and it needs decisions about how an inquiry
  reaches the operator.
- Product copy in the prototype is placeholder. Real names, leathers, prices,
  dimensions and body copy are still needed before launch.
