# SDD ledger — plan: docs/superpowers/plans/2026-08-09-filament-admin.md

Worktree: C:\Users\Matin Asgarov\leather-shop\.claude\worktrees\filament-admin
Branch: worktree-filament-admin
Baseline: 90 passed, 1 skipped at f32e41d
Setup commit: 25aa784 (ignore harness worktree directory)

## Pre-flight

Plan scan found one conflict: Tasks 3+4 mandated the same money-conversion
closure pair verbatim on six price fields (rubric calls that Important
duplication). Human ruling: extract `MoneyInput::field()`. Plan amended in
99ff454 before Task 1 dispatch.

## Tasks

Task 1: BASE 99ff454. Implementer a375b74b411b0edb0 (sonnet).
Task 1: first pass deddfa1 — DONE_WITH_CONCERNS, 14/14 new, suite 104/1 skipped.
Task 1: controller ruling — implementer dropped the FK on `order_events.user_id`
  to accommodate the brief's `userId: 42`. Spec §4.5 requires the FK. Ruled:
  restore the FK, fix the test to create a real user, add a constraint-is-live
  test. Same class of defect as Plan 1 Task 9 (bare id vs real FK).
  `OrderItem::order()` belongsTo addition approved as necessary.
Task 1: FK fix 0079064 — DONE, 15/15 TransitionTest, suite 105/1 skipped.
Task 1: review at 99ff454..0079064 (opus) — Needs fixes.
  CRITICAL: gateway refund fires before the legality check; MockGateway::refund
  has no idempotency guard, so a repeated refund action moves real money twice
  and surfaces only "illegal transition". PLAN-CAUSED — my plan text specified
  that ordering. Treated as a plan omission (a missing guard), not a plan
  decision, so fixed without a human ruling — same category as Plan 1 Task 12's
  uncaught createPayment().
  IMPORTANT: markReady() has no status guard, writes ready_at on cancelled or
  unpaid orders, and leaves no audit trail.
  Promoted one minor into the fix round: transition() leaves the caller's $order
  stale — Tasks 4 and 5 drive it from Filament actions holding a record instance,
  so this is load-bearing cross-task context the reviewer lacked.
Task 1: minor (deferred): dead `$refund = null;` initialization in transition().
Task 1: minor (deferred): app(PaymentGateway::class) resolved twice; class_basename
  writes 'MockGateway' into payment_logs.gateway where MockGateway's own rows say
  'mock' — two spellings for one gateway.
Task 1: minor (deferred): trackingNumber silently ignored for non-Shipped targets.
Task 1: minor (deferred): declined-refund test does not assert the failed attempt
  was logged to payment_logs, leaving that deliberate behaviour unpinned.
Task 1: fix round 1/5 (3 addressed, 0 open; commits 0079064..dfccc6a).
Task 1: complete (commits 99ff454..dfccc6a, review clean). 17/17 TransitionTest,
  suite 107 passed / 1 skipped.

Task 2: BASE dfccc6a. Implementer a18b69692e420f924 (sonnet).
Task 2: first pass 9486cf6 — DONE, PanelAccessTest 6/6, suite 113/1 skipped.
  Filament v5 installed.
Task 2: controller correction — commit tracked ~4.4 MB of published Filament
  assets (public/css|js|fonts/filament, incl. woff2 binaries). These are
  `filament:assets` output, regenerated on deploy with no Node needed, so they
  go stale on upgrade and made the review package 4.5 MB. Ruled: gitignore and
  `git rm --cached`. Distinct from /public/build, which Task 6 deliberately
  tracks because the host cannot rebuild Tailwind without Node.
Task 2: fix round 1/5 (1 addressed, 0 open; commits 9486cf6..4cee687).
Task 2: complete (commits dfccc6a..4cee687, review clean). 113 passed, 1 skipped.
Task 3: BASE 4cee687. Implementer aad4b599 (sonnet), interrupted mid-task by a
  host restart; resumed from saved transcript with uncommitted work intact.
Task 3: controller-approved deviation — Filament 5.7 pulls in Livewire v4, no
  Pest-v3-compatible pest-plugin-livewire release supports it. Asked human
  partner: approved upgrading Pest 3->4, PHPUnit 11->12 project-wide. Split
  into its own commit d8bfd44 ahead of the feature commit.
Task 3: controller-approved deviation — make:filament-resource --generate
  hung/needed a real sqlite file; hand-wrote ProductResource per the brief's
  own fallback clause. Verified faithful to brief's specified code by review.
Task 3: DONE_WITH_CONCERNS a5e3622 (product resource) on top of d8bfd44
  (Pest/PHPUnit upgrade). Full suite 127 passed/1 skipped (baseline 113+1,
  +14 new); targeted MoneyInputTest+ProductResourceTest 14/14, 57 assertions.
Task 3: review at 4cee687..a5e3622 (sonnet) — Spec compliant, Approved.
  No Critical/Important findings.
Task 3: minor (deferred): ProductResource.php import order non-alphabetical,
  cosmetic, a pint pass would fix it.
Task 3: minor (deferred): MoneyInput UI regex caps input at 2 decimals, so
  toMinor's half-up-on-3rd-decimal rounding path is unreachable from any
  panel field in practice (only the unit test exercises it) — not a defect,
  matches brief verbatim, just dead code in practice.
Task 3: complete (commits 4cee687..a5e3622, review clean, 2 controller-
  approved deviations).
Task 4: BASE a5e3622. Implementer a86faf3a (sonnet). DONE a36dd42.
  Full suite 145 passed/1 skipped (baseline 127+1, +18 new: 11 OrderResourceTest
  + 7 SupportingResourcesTest).
Task 4: disclosed deviation — added Order::paymentLogs() and PaymentLog::order()
  relations, not in brief's file list, needed for PaymentLogResource's
  order.order_number column. Not pre-approved; reviewer verified payment_logs.order_id
  is a real nullable FK column, so ruled reasonable/minimal/additive, not overreach.
Task 4: review at a5e3622..a36dd42 (sonnet) — Spec compliant, Approved. Named-risk
  check: grepped full app/ tree, OrderService::transition()/markReady() called from
  exactly one site each, both inside TransitionActions. No direct orders.status
  writes anywhere in the diff. No Critical/Important findings.
Task 4: minor (deferred): ViewOrder.php personalization formatStateUsing's
  multi-key-array branch is unverified by any test (only single-key case exercised).
Task 4: minor (deferred): DiscountCodeResource's `value` field concentrates 4
  kind-branched behaviors in one field def — fine for 2 kinds, revisit if a 3rd
  discount kind is ever added.
Task 4: complete (commits a5e3622..a36dd42, review clean, 1 disclosed deviation
  ruled reasonable).
Task 5: BASE a36dd42. Implementer a6f4962b (sonnet), interrupted mid-task by a
  host restart; resumed from saved transcript with uncommitted work intact.
Task 5: DONE 676131a. Full suite 155 passed/1 skipped (baseline 145+1, +10
  WorkshopTest). PanelAccessTest "lets an operator in" reconfirmed since
  Workshop replaced Dashboard as /admin home.
Task 5: self-flagged concern — TransitionActions.php's 3 visible() closures
  widened Order -> ?Order to survive Workshop's pre-mount header render.
  Review verified all 3 closures null-safe (?->, ??, or leading guard) and
  ViewOrder.php's usage unaffected (always route-bound, never null). Confirmed
  behaviorally inert as claimed.
Task 5: review at a36dd42..676131a (sonnet) — Spec compliant, Approved.
  No Critical/Important findings.
Task 5: minor (deferred): Workshop.php's $resolveRecord closure + ->after()
  callback duplicated 3x across action registrations, small enough to leave.
Task 5: minor (deferred): both getRoutePath() override and ->homeUrl() set in
  AdminPanelProvider; only the route-path override is strictly necessary,
  homeUrl likely redundant but harmless.
Task 5: complete (commits a36dd42..676131a, review clean).
Task 6: BASE 676131a. Implementer a0b500a6 (sonnet). DONE_WITH_CONCERNS 61912e3.
  Full suite 156 passed/1 skipped (baseline 155+1, +1 throttle test).
Task 6: disclosed deviation — Filament's admin login is Livewire-only (no
  dedicated POST route; brief's two suggested throttle hooks don't exist).
  Added a parallel POST /admin/login route in routes/web.php with its own
  Auth::attempt() call, guarded by throttle:admin-login. Not pre-approved.
Task 6: review at 676131a..61912e3 (sonnet) — Needs fixes. CSRF/route-collision/
  no-bypass-elsewhere all verified clean. Important: new route authenticates
  without checking is_operator (weaker gate than Filament's real
  attemptWhen(...isUserAllowedToAccessPanel...)). Important: the rate limiter
  only guards the new decoy route — real login goes through Livewire's
  /livewire/update, which already has Filament's own pre-existing, unrelated
  throttle; task's stated hardening goal doesn't reach the real attack surface,
  needs to be stated plainly rather than buried.
Task 6: fix round 1/5 dispatched — same implementer, both findings.
Task 6: fix round 1/5 (2 addressed, 0 open; commits 61912e3..083f0ce).
  is_operator mismatch now tears down session (logout+invalidate+regenerateToken)
  and manually dispatches Failed event so it's logged/throttled, not a silent
  partial-login. Comment + report now state plainly that Filament's real login
  has its own separate pre-existing throttle, unaffected by this route.
Task 6: complete (commits 676131a..083f0ce, review clean after 1 fix round).
  Final suite: 156 passed, 1 skipped.

## All tasks complete. Proceeding to final whole-branch review.

## Final whole-branch review (f32e41d..083f0ce, opus)

Central invariant verified across the whole tree: OrderService::transition()/markReady()
called from exactly one place each (TransitionActions.php); the only other orders.status
writer is ReleaseExpiredReservations (Plan 1, explicitly sanctioned). No undisclosed
deviations found. Both disclosed deviations (Pest 4 upgrade, POST /admin/login fallback)
hold up with full-branch context.

Critical:
1. deploy.md omits `composer install` — following it verbatim on a clean deploy leaves
   vendor/ without Filament, migrate fatals.
2. Nothing processes the mail queue in production (no worker, default `database` queue
   driver) — ShipmentNotification/OrderConfirmation/PaymentAnomaly all queue silently
   forever. deploy.md's cron section falsely claims cron processes queued mail.

Important:
3. EditProduct/VariantsRelationManager offer unguarded DeleteAction on ordered
   products/variants — cascades break order_items FK, corrupts restoreCapacity() for
   historical orders. Not requested by the plan; implementer-added affordance.
4. TransitionActions::markReady() bypasses run()'s try/catch — a stale-page mark-ready
   throws uncaught IllegalTransitionException instead of a red notification like every
   other action.
5. POST /admin/login route's comment overstates protection — CSRF middleware runs
   before throttle, so the "direct scripted POST" case it claims to guard actually
   gets 419, never reaches the throttle/Auth::attempt/Failed listener. Security posture
   is fine (419 is a better outcome), but comment/test don't reflect reality.
6. ShippingZoneResource/RatesRelationManager have no validation that a fallback zone
   exists or that rate brackets have no gaps — operator can silently zero out checkout
   quotes for real customers via panel actions with no warning.

Minor promoted to fix-now: payment_logs.gateway spelling split ('MockGateway' via
  class_basename vs 'mock' written directly by MockGateway) — now visible on-screen
  in PaymentLogResource/ViewOrder, was invisible when Task 1 deferred it.

Other minors: triaged, correctly deferred (import order, unreachable rounding path,
  untested personalization branch, 4-way discount value field, 3x closure duplication,
  Failed-listener label, homeUrl redundancy, TransitionActions' remaining 3 non-widened
  visible() closures, minValue-without-numeric on discount value, discount kind-switch
  reinterpretation, PaymentLogResource nested-payload rendering, WorkshopTest coverage
  gap on mark_ready/ship, deploy.md missing filament:optimize/view:cache/payment-driver
  caveat).

Assessment: Ready to merge, with fixes. Dispatching ONE fix subagent for the 2
Critical + 4 Important + 1 promoted-minor findings.
Final review fix wave: implementer a5f08953 (sonnet). DONE cdd300a — all 7
  findings (2 Critical, 4 Important, 1 promoted minor) in one commit.
  Suite 163 passed/1 skipped (baseline 156+1, +7 new tests).
  Disclosed deviation: skipped the OPTIONAL 419-CSRF pinning test for finding 5
  (Laravel disables CSRF validation under the test runner by design); the
  required part of finding 5, the comment correction, was done.
Final review fix wave: scoped re-review dispatched at 083f0ce..cdd300a.
  (First attempt failed on session rate limit, re-dispatched.)
Final review fix wave: re-review at 083f0ce..cdd300a (sonnet) — All 7 findings
  ADDRESSED, no new Critical/Important breakage. Finding 6's validation verified
  to cover BOTH directions (turning off the last fallback, and creating a first
  non-fallback zone in an empty system), each pinned by its own test.
Final review fix wave: out-of-scope observation (non-blocking, pre-existing) —
  EditDiscountCode and EditShippingZone/RatesRelationManager still have
  unguarded DeleteAction::make(). Neither model is referenced by order_items,
  so neither carries finding 3's order-history-corruption risk.

## PLAN 2A COMPLETE. All 6 tasks + final review + fix wave done.
## Final state: cdd300a, 163 passed / 1 skipped.
