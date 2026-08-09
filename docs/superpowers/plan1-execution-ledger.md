# SDD ledger — plan: docs/superpowers/plans/2026-08-08-commerce-engine.md

Worktree: C:\Users\Matin Asgarov\leather-shop\.worktrees\commerce-engine
Branch: commerce-engine (from master @ 36ae60c)
Started: 2026-08-08

## Pre-flight

Conflict scan run before Task 1 dispatch. Three findings:

1. **Fixed in plan (commit 5767d8a).** Task 13's tests asserted
   `Mail::assertNothingSent()` on forged-signature and unknown-reference
   callbacks, but Step 4 of the same task sends an operator alert on exactly
   those branches. The task would have failed its own tests. Narrowed both
   assertions to `Mail::assertNotSent(OrderConfirmation::class)`.

2. **Human ruling — plan governs.** Task 15's oversell test runs two order
   attempts sequentially rather than in parallel processes. Kept, and renamed
   to state what it actually verifies (stock check inside the locked
   transaction) — commit ee94a89. Reviewers may still flag it; this ruling
   stands.

3. **Human ruling — plan governs.** Task 10's `env('PAYMENT_MOCK_SECRET',
   'test-secret')` default is kept. Applies only to MockGateway, which never
   moves real money; the Epoint secret will have no default.

## Tasks

Task 1: review clean (spec ✅, quality Approved) at ff30b41.
Task 1: controller-resolved ⚠️ items — php.ini extensions verified loaded
  (all 8 + mbstring/openssl/curl; pdo_pgsql correctly absent);
  php.ini backup confirmed at C:\php\php.ini.backup-before-leathershop.
Task 1: minor (deferred): config/database.php hand-edit guards
  PDO::MYSQL_ATTR_SSL_CA behind Pdo\Mysql::ATTR_SSL_CA for PHP 8.5. Verified
  correct and durable across composer update; outside the brief's literal
  file list. Flag to final review.
Task 1: fix round 1/5 dispatched — controller-confirmed real gap from
  cross-task context: tests/Pest.php has RefreshDatabase commented out and
  binds only 'Feature'. Breaks Task 3 onward (state leaks between tests) and
  Task 4 specifically (tests/Unit/Cart/PersonalizationValidatorTest.php needs
  the app booted + a database). Resumed implementer a95efc254d2031010.
Task 1: fix round 1/5 (1 addressed, 0 open — Pest RefreshDatabase + Unit binding; commits ff30b41..ff1a50f)
Task 1: complete (commits e17f9c3..ff1a50f, review clean)
Task 2: review found Important (plan-mandated) float misrounding in Money::percentOf.
  Verified independently: percentOf(375,9.2)=34 (needs 35); but 2,000,000 integer-percent
  cases showed 0 mismatches, so the defect was unreachable given discount_codes.value is
  unsignedInteger. Human ruling: FIX with int percent + exact integer arithmetic.
  Plan document updated to match (commit 774364a).
Task 2: fix round 1/5 (1 addressed, 0 open — exact integer arithmetic; commits 9a60091..774364a)
Task 2: complete (commits ff1a50f..774364a, review clean)
Task 3: review clean (spec ✅, quality Approved) at f026b6f.
Task 3: controller-resolved ⚠️ — ran full suite: 8 passed (14 assertions), pristine.
Task 3: minor (deferred): Product::images() adds ->orderBy('sort_order') which the brief's
  interface line omitted (the brief's code block does include it). Non-issue; noted for final review.
Task 3: fix round 1/5 dispatched — controller-confirmed real gap (pre-existing from Task 1,
  surfaced by this review): phpunit.xml leaves the :memory: overrides commented, so tests run
  against database/database.sqlite and RefreshDatabase wipes the dev DB every run. Makes Task 15
  self-defeating (Step 6 seeds, Step 7 wipes). Resumed implementer a952de644f1fd2c3c.
Task 3: fix round 1/5 (1 addressed, 0 open — phpunit.xml :memory: isolation, canary row proof; commits f026b6f..e70669b)
Task 3: complete (commits 774364a..e70669b, review clean)
Task 4: complete (commits e70669b..04d2525, review clean — no fix round)
  Whitelist-by-construction verified; mb_* string handling correct for AZ characters;
  preg_match(...)!==1 fails closed.
Task 4: minor (deferred): pattern-violation message not asserted by any test (class only).
Task 4: minor (deferred): no explicit indexes beyond implicit FK indexes.
Task 4: security-review findings (background) ADJUDICATED — not exploitable, no fix round:
  (a) "PCRE allowlist escape / $ before trailing newline": trim() runs before preg_match, so
      "AB\n" normalizes to "AB" and no control char is stored; interior "A\nB" is rejected.
      Verified against the running validator.
  (b) "ReDoS via DB-controlled pattern": pcre.backtrack_limit aborts /^([A-Z]+)+$/ in ~2ms
      returning false; validator's !==1 fails closed. Pattern is admin-set, not customer input.
Task 4: minor (deferred): a malformed admin-set allowed_pattern emits a PHP warning before
  failing closed — noise, not a bypass. Flag to final review.
Task 5: implementer a3ca45cc674fbcf30 terminated mid-task by session limit after RED confirmed.
  Uncommitted partial work on disk: app/Domain/Cart/CartLine.php, tests/Feature/Cart/CartServiceTest.php.
  Missing: CartSnapshot.php, CartService.php. Resuming same agent (context intact).
Task 5: review clean on the critical property (no price in session; snapshot() recomputes from DB;
  test 2 genuinely pins it). Interfaces match Task 9's expectations exactly.
Task 5: review found Important (plan-mandated): max(1, $quantity) silently coerces 0/negative to 1.
  Human ruling: FIX — throw InvalidQuantityException on quantity < 1. Plan document updated.
Task 5: minor (deferred): remove() and clear() have no test coverage (inherited from brief).
Task 5: minor (deferred): "product inactive" drop path untested (only variant-inactive is).
Task 5: fix round 1/5 dispatched — resumed implementer a3ca45cc674fbcf30.
Task 5: fix round 1/5 (1 addressed, 0 open — InvalidQuantityException guard; commits 290e3d2..12763bb)
Task 5: complete (commits 04d2525..12763bb, review clean)
Task 6: complete (commits 12763bb..e6890db, review clean — no fix round)
  quoteById() verified to re-derive quotes rather than trusting a rate id (blocks AZ-rate-on-DE-order).
  Bracket inclusivity <=/>= both ends; over-weight returns []; country code uppercased.
Task 6: controller-resolved ⚠️ — ran full suite, pristine (see count below).
Task 6: minor (deferred): zoneFor() tie-break undefined if two zones list the same country
  (no orderBy('id')); latent, not exercised — admin-managed data.
Task 6: minor (deferred): no test for lowercase country code although code handles it.
Task 6: minor (deferred): migration comment says empty country_codes means catch-all, but only
  is_fallback drives it.
Task 7: review found Important (plan-mandated): apply()/consume() TOCTOU lets a code exceed
  usage_limit under concurrent checkouts. Human ruling: consume() becomes conditional+atomic and
  returns bool; Task 11 honours the paid order and alerts the operator on false.
  Plan updated (a8b8a58) incl. moving PaymentAnomaly + config/shop.php from Task 13 to Task 11.
Task 7: fix round 1/5 (1 addressed, 0 open — atomic limit-aware consume; commits 16433fe..a8b8a58)
Task 7: complete (commits e6890db..a8b8a58, review clean)
Task 7: minor (deferred): whereRaw UPPER(code) defeats an index on code (irrelevant at this scale).
Task 7: minor (deferred): no standalone test for the is_active=false rejection path.
Task 8: review found 2 Important (both plan-mandated).
  (a) snapshot test asserted literals back — could not fail. Human ruling: REWRITE as a real test
      (create product/variant, mutate after write, re-read) + add a nullOnDelete test.
      Implementer proved it can fail by flipping an assertion.
  (b) unsigned money columns cannot hold negatives. Human ruling: ACCEPT AS-IS (no refunds in v1;
      unsigned guards sign errors; widening is a one-line migration later).
Task 8: fix round 1/5 (1 addressed, 1 accepted-by-ruling; commits 2fabd11..048ea30)
Task 8: complete (commits a8b8a58..048ea30, review clean) — 39 tests passing
Task 8: minor (deferred): OrderStatus::label() has no test coverage.
Task 9: implementer escalated a genuine plan defect before guessing (correct behaviour):
  the brief's tests build ShippingQuote(rateId: 1)/DiscountResult(codeId: 1) as bare value objects,
  but Task 8's migration puts real FKs on orders.shipping_rate_id / discount_code_id, so the insert
  fails with "FOREIGN KEY constraint failed". Controller ruling (no human ruling needed — only one
  resolution is consistent with the approved Task 8 schema): the TEST creates real ShippingZone/
  ShippingRate/DiscountCode rows and uses their auto-assigned ids. Service and migration untouched.
  Added assertions pinning order.shipping_rate_id and order.discount_code_id to the real rows.
Task 9: implementer committed aec055b then was killed by session limit before writing its report.
  Controller independently verified: 46 passed, 91 assertions, pristine.
Task 9: complete (commits 048ea30..5ade1ed, review clean — no fix round)
  Atomicity verified (order row + stock both roll back; test asserts Order::count()==0);
  lockForUpdate() correctly placed BEFORE the stock comparison; snapshots copied at write time.
  Plan updated (5ade1ed) to fix the same FK defect in Task 15's test before it bites.
Task 9: minor (deferred): redundant per-line Variant::whereKey(...)->value('sku') re-queries the
  row reserveStock() just locked — N extra queries per order. Fix: return the locked Variant.
Task 9: minor (deferred): empty cart reuses InsufficientStockException (conflates two failure modes).
Task 9: minor (deferred): task-9-report.md missing (agent died before writing it).
Task 10: complete (commits 5ade1ed..98d627a, review clean — no fix round) — 52 tests passing
  hash_equals verified (not ===); container binding default arm THROWS (no silent mock fallback);
  invalid callbacks still logged; isPaid cannot be true without a valid HMAC;
  no concrete gateway referenced outside app/Domain/Payment/.
Task 10: minor (deferred): refund() logs direction='request' rather than a distinct 'refund' value.
Task 10: minor (deferred): forged callbacks still run an Order lookup for the log's order_id.
Task 11: complete (commits 98d627a..e9f6248, review clean — no fix round) — 58 tests passing
  Idempotency guard verified INSIDE the locked transaction; mail sent after commit;
  cancelled orders not resurrected; over-redeem path still marks Paid + alerts operator.
  Operator-alert plumbing (config/shop.php, PaymentAnomaly, blade view) created here per the
  Task 7 ruling; Task 13 must REUSE it, not recreate it.
Task 11: note (not a defect): PaymentAnomaly's optional $ip param was specified by the controller
  deliberately for Task 13's reuse; unused in this task.
Task 11: minor (deferred): markPaid uses $order->fresh() for the mail — would throw if the order
  were deleted between commit and send. No deletion code exists anywhere in app/, so unreachable.
Task 12: review found Important — createPayment() uncaught. Not plan-mandated: the DESIGN SPEC's
  failure table requires "Gateway unreachable -> customer message, order saved and unpaid" and the
  plan simply omitted it. No human ruling needed; fixed as a spec gap.
Task 12: fix round 1/5 (1 addressed, 0 open — try/catch + log + cart preserved; commits e395fbc..53e5821)
Task 12: complete (commits e9f6248..53e5821, review clean) — 65 tests passing
  Verified: validation whitelist makes browser-supplied totals structurally unreachable;
  createFromCart() strictly precedes createPayment(); cart cleared only on success.
Task 13: complete (commits 53e5821..d611f9a, review clean — no fix round, zero issues) — 72 tests
  Signature verified first (no state change on invalid); idempotency delegated to markPaid rather
  than double-guarded; duplicates return 200 so gateways stop retrying; CSRF exemption scoped to
  the exact literal 'payment/callback'; both anomaly branches log AND email the operator with IP.
  Task 11's mail plumbing reused, not recreated (verified absent from this diff).
Task 14: complete (commits d611f9a..12d0086, review clean — no fix round) — 76 tests passing
  Lock-and-recheck verified INSIDE the transaction; reserved_until nulled in the same update as the
  status flip (that is what makes a rerun a no-op); atomic increment(); null variant_id guarded.
  Reviewer cross-checked markPaid() takes lockForUpdate() on the same row — the two paths serialize
  at the DB level, which is the real mechanism behind property 1.
Task 14: minor (deferred): job reads $order->items from the pre-lock object (safe: items are
  immutable after creation, but the invariant is implicit).
Task 14: minor (deferred): no test for an order_item with null variant_id.
Task 14: ⚠️ noted: no test exercises the actual select-then-payment race; single-process tests
  cannot. Task 15's MySQL test is the closest available proxy.
Task 15: complete (commits 12d0086..85ab087, review clean — no fix round)
  Seeder counts verified from code (3 products / 8 variants / 6 rates / 1 code); SKUs unique;
  fallback zone correct; mysql_test uses port 3307 + InnoDB (MyISAM would silently ignore locks).
  Skip guard verified: phpunit.xml hardcodes sqlite, so OversellTest CANNOT silently pass on SQLite.
Task 15: BLOCKER (open, disclosed): the MySQL OversellTest has NEVER EXECUTED — Docker daemon down.
  Oversell protection is UNVERIFIED on the only engine where lockForUpdate() does anything.
  "1 skipped" must not be read as a pass. Requires Docker Desktop running: docker compose up -d mysql
  then $env:DB_CONNECTION="mysql_test"; php artisan test --filter=OversellTest
Task 15: minor (deferred): implementer fixed a bug in the brief's seeder data (3+2+2 colours = 7
  variants, not the required 8); added a third Card holder colour. Reviewer judged it coherent.
Task 15: minor (deferred): docker-compose.yml carries --default-authentication-plugin=caching_sha2_password
  verbatim from the brief; that flag is removed in some MySQL 8.4 builds and may break startup.

ALL 15 TASKS COMPLETE. 76 passed + 1 skipped. Proceeding to final whole-branch review.

=== FINAL WHOLE-BRANCH REVIEW (36ae60c..85ab087, 29 commits) — Mergeable with fixes ===
CRITICAL
 C1 Late-but-genuine payment swallowed. Job cancels expired order + returns stock; callback then
    arrives; markPaid() returns false; controller DISCARDS the bool and returns 200. Customer charged,
    order cancelled, stock possibly resold, NO alert/log/email. Fix: distinguish "already paid" from
    "not payable"; alert operator on the latter.
 C2 No amount verification. CallbackResult carries no amountMinor/currency; markPaid takes no amount;
    nothing compares paid vs orders.total_minor. Mock HMAC covers reference|status only. Must fix
    BEFORE EpointGateway or the "low-risk swap" premise fails.
IMPORTANT
 I1 PAYMENT_DRIVER defaults to 'mock' AND /payment/mock/{reference} is routed unconditionally ->
    a deploy missing one env var is a free-goods store. Gate route+binding to local/testing.
 I2 payment_reference persisted AFTER createPayment() returns -> callback can 404 on a real payment.
 I3 Callback route unauthenticated + CSRF-exempt + unthrottled + emails operator per request ->
    unbounded mail/row amplification. Add throttle.
 I4 Confirmation mail sent synchronously; transport failure = 500, gateway retries, markPaid now
    false, email NEVER sent. Use ->queue().
 I5 docker-compose --default-authentication-plugin removed in MySQL 8.4 -> container crash-loops;
    this GATES the only open blocker.
 I6 Cart cleared before payment, contradicting spec 5 "cart preserved"; no retry link.
SPEC GAP: order lookup by email + order number (spec 3, v1) NOT BUILT — and the confirmation email
  promises it ("Track your order any time with your email address and order number").
NOTE: OversellTest would pass on SQLite if the guard were removed — it proves stock exhaustion, not
  lock contention. Oversell protection verified by reading only, and the reading is favourable.

FINAL FIX WAVE: commits 85ab087..d31a835 (a6231ff C1+C2, ee97d10 I1-I6, d31a835 order lookup).
Scoped re-review: ALL findings ADDRESSED, no new Critical/Important breakage. BRANCH MERGEABLE.
  Verified: idempotency survived the C1 refactor (status check still inside the lock, 3 duplicate
  callbacks -> 1 queued mail); AmountMismatch leaves the order untouched for a human to resolve;
  the HMAC signing string agrees across MockGateway, the mock Blade form and the test helper;
  order lookup returns a byte-identical response for wrong-email vs unknown-number (no existence leak);
  cart cleared exactly once, only when the order is Paid.
Honest substitution accepted: the "mock route 404s in production" test was impractical (phpunit forces
  APP_ENV=testing at boot); a binding-throws-in-production test was substituted. Route guard uses the
  identical environment predicate — verified by inspection, no divergence.
Minor (deferred): CheckoutController hardcodes 'MOCK-'.$order->order_number, duplicating MockGateway's
  reference format. Latent coupling if a non-deterministic gateway is added — relevant when EpointGateway lands.

STATE: 90 passed, 1 skipped, 207 assertions, pristine. Tree clean at d31a835.
OPEN BLOCKER (unchanged): OversellTest never executed — Docker daemon down.
