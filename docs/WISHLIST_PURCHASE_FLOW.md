# Ordinary wishlist purchase flow

This slice implements the ordinary authenticated wishlist purchase journey described in [PRODUCT_DEFINITION.md](PRODUCT_DEFINITION.md), section 4C and Journey 1 steps 7–8, plus priority ordering and buyer coordination on the ordinary wishlist page. It builds on the Secret Santa privacy implementation already in `master`; it does **not** replace Secret Santa assignment authorization or claim that every part of Journey 3 is complete.

## Display and privacy contract

- Items are ordered by `HIGH`, `MEDIUM`, `LOW`, then `created_at DESC`, then `id DESC`, before `simplePaginate(20)`. The explicit priority ordering avoids lexical enum order, and the final unique tie-breaker keeps pages stable.
- Owners see a whole-wishlist count of items in `BOUGHT` or `RECEIVED`, including items outside the current page. The count excludes other wishlists.
- Owner item queries omit `status`, `bought_by_user_id`, `bought_at`, and `updated_at`. Owner cards have no status-dependent badge, availability text, dimming, or purchase action. This is query-level omission, not just hidden Blade text.
- Other authorized viewers retain item status for purchase coordination, but receive neither buyer identity nor purchase timestamps. `BOUGHT` and `RECEIVED` both display as bought.
- Owner calls to the purchase action are rejected before status-dependent lookup, including probes for available, bought, received, or nonexistent items.
- Archived wishlists remain readable to otherwise authorized viewers, but new purchases are forbidden and purchase controls disappear. This slice does not redesign owner editing or item creation on archived lists.
- Aggregate counts are not cryptographic secrecy: a one-item list or observations of count changes can support inference. Existing item content and external store links are not anonymized.

## Final-write authorization

`WishlistItem::markAsBought()` performs a conditional update that requires all of the following at the write itself:

1. The item still belongs to the original wishlist and is `AVAILABLE`.
2. The buyer still exists and is not the wishlist owner.
3. The wishlist retains its original owner and is not archived.
4. Current ordinary visibility permits the buyer: an explicit share for `SPECIFIC`, or buyer/owner membership in a common active family for `FAMILY`.

`PRIVATE` lists do not become purchasable through a stale share or family membership. Family visibility preserves the existing common-active-family rule; it is not restricted to the optional wishlist `family_id`. Only an update of exactly one row succeeds and dispatches a notice. A failed final write does not overwrite a competing purchase and does not enqueue mail.

The mounted component also refreshes and authorizes the wishlist, scopes item lookup to it, and rejects owners/archived lists before looking up availability. Revocation interleavings are covered with controlled SQLite `beforeExecuting` hooks immediately before the real update. These tests demonstrate the final SQL predicates; they are not multiprocess load tests or PostgreSQL/MySQL locking qualification.

## Generic owner notice

After a successful ordinary purchase, `SendWishlistPurchaseNotice` is dispatched with `afterCommit()` on the `mail` queue. Its payload carries only item, wishlist, and original-owner IDs—not an email address, item description, or buyer identity.

At delivery the worker reloads current state and sends only if:

- the original wishlist still exists with the original owner and is not archived;
- the original item still belongs to it and is `BOUGHT` or `RECEIVED`;
- the current owner record still exists.

The worker resolves that owner's current email and locale. English and `pt_BR` are supported, with English fallback for unsupported locales. Both HTML and plain text are generic: no gift name, buyer identity, wishlist name, price, or purchase timestamp. The action links to the existing authenticated wishlist page; it does not grant new access.

The job uses three tries, a 30-second timeout, and 10/60/180-second backoff. Continue supervising `php artisan queue:work --queue=mail,default --tries=3 --timeout=30`, with `retry_after` greater than the timeout (the current database default is 90 seconds). A `sync` connection still runs delivery synchronously by explicit configuration. Worker/Octane reloads are rollout operations, not performed by this source change.

`afterCommit()` suppresses rolled-back work; it is not a transactional outbox. A process crash between the successful database write/commit and enqueue can lose a notice. Once queued, delivery is at-least-once and transport retries can duplicate email. There is no exactly-once claim. Real SMTP delivery and production operations are not qualified here.

## Secret Santa boundary and exclusions

`AssignmentAccess::markAsBoughtFor()` retains its separate event, assignment, accepted-participant, consent, and lifecycle predicates. It does not call the ordinary purchase method, and this slice does not add the ordinary email notice to that path. An owner viewing their ordinary wishlist still sees the aggregate of persisted purchase states regardless of which authorized path set them.

No schema or dependency change is included. Reservation expiry, undo/unbuy, received-item transitions, automatic URL price extraction, persistent domain notifications, and Secret Santa completion/archive/history remain separate work.

## Verification snapshot before publication

The candidate was composed with `master` at `d0f5baaa77bab10aef7a0d762efd1679e45204c2`. Results below distinguish local qualification from hosted CI:

| Gate | Evidence |
|---|---|
| Focused purchase view, mutation, and queue suites | **47 passed, 315 assertions**, rerun after base integration |
| Real database queue | Commit/rollback, one-shot worker, generic HTML/plain-text mail, stale-state suppression, current email/locale, and zero failed jobs covered in the focused integration suite |
| Browser journeys | **5 cases passed per locale** in English and Portuguese: actual authenticated owner/buyer pages, priority/aggregate privacy, persisted Livewire purchase, owner reload, and real database-worker delivery through array transport; desktop/mobile checked |
| Release smoke | Cached production-mode config/routes/views; all **17 migrations**, rollback and reapply on disposable SQLite; resources removed afterward |
| Repository-wide Pint | Passed after base integration |
| Portable Vite build | Passed after base integration |
| Composer validation/platform requirements/audit, npm audit, tooling suite | Passed during this slice; tooling **11 passed**; dependency locks unchanged by base integration |
| Full PHP suite with coverage and PHPStan | **NOT RUN to completion locally**: the unchanged shared-host resource guard denied admission; no pass inferred from focused tests |
| Native Vite build on this VM | **Failed with SIGILL (exit 132)** in native Rolldown; portable success is not a native-build pass |
| Exact-head hosted CI | **Pending publication** in this frozen document; the PR must record the actual final run and supersede this pending status before becoming ready |

Required hosted gates are PHP quality/dependency audit, native and portable frontend checks, and the full PHP suite with at least 80% coverage plus release smoke. Independent source review is separate from those gates and from merge/deployment approval.

Regression coverage also includes stable priority pagination, owner projection-key absence and uniform probes, revoked shares, family/owner/buyer changes at the final update, item moves/deletion, competing purchases, and suppressed jobs after unsuccessful writes. Browser checks use synthetic accounts and block external network requests; array-mail assertions do not prove inbox delivery.
