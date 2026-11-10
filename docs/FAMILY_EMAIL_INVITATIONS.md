# Family email invitations

This slice implements the email invitation and accept/reject journeys from [PRODUCT_DEFINITION.md](PRODUCT_DEFINITION.md#b-family-management). It is independent of the Secret Santa privacy slice. Existing code-based family joining is unchanged; QR invitations, WhatsApp delivery and persistent domain notifications are not included.

## User journey

- An active family's creator or administrator sends an invitation from the family roster. The email is trimmed, lowercased and validated; existing members/creators and still-pending unexpired invitations cannot be invited again.
- Sending creates a `PENDING` invitation, **not** a membership. The UI says the email is queued, not delivered.
- The email contains a personal, signed review link that expires seven days after issuance. It does not contain the family's reusable invite code.
- The recipient signs in or registers with the invited email address. The intended URL survives either journey. Opening a link never joins the family.
- Explicit, CSRF-protected acceptance creates a `MEMBER` membership and opens the family. Declining creates no membership. Existing membership roles are preserved, and a terminal response cannot be replayed.
- Administrators see invitation history in pages of ten, with status and expiry. They can cancel a pending invitation, then send another to the same address. Expired/declined invitations can also be reissued; each reissue replaces the generation and invalidates old links and queued jobs.
- Ordinary members cannot see invitation email history or management controls. Archived families remain readable according to the existing policy, without invitation mutation controls.
- The new invitation UI, email and response copy have English and Brazilian Portuguese catalogs. This is not a full localization sweep of the pre-existing family shell. Mail uses the worker's configured application locale; per-recipient language preferences are not implemented.

## Authorization and persistence

`FamilyInvitationService` owns send/cancel mutations. Its conditional writes include current family activity and sender authority; creation/reissue also excludes existing membership at the write. There is one database-enforced invitation record per `(family_id, email)`.

`FamilyInvitationController` requires `auth` and `auth.session`, a valid signature/expiry, the current generation, an active family and a currently authorized inviter. The authenticated user's current normalized email must match the invitation; case-colliding accounts fail closed. This personal-link-plus-authenticated-account boundary does not add global email verification.

Acceptance first conditionally transitions `PENDING` inside a transaction, then conditionally inserts membership using the current invitation, recipient, generation, lifecycle and inviter predicates again. Existing memberships are not overwritten. Tests inject revocations immediately before the final write to ensure earlier authorization reads cannot authorize a stale mutation.

Cancelled, expired, superseded, wrong-account and otherwise invalid links return a generic unavailable response without the family name. Accepted/declined links show the terminal result while otherwise valid. The review response is private/no-store with a no-referrer policy.

The additive migration is `2026_11_10_000003_create_family_invitations_table.php`. It references families and inviters with cascading foreign keys. Rollback removes invitation records; it does not undo accepted family memberships. Back up invitation data before any operational rollback. No production database was changed during qualification.

## Queue and operational requirements

`SendFamilyInvitation` routes to `mail`, is dispatched after commit, and carries only the invitation ID and generation. The worker resolves current state before rendering/sending and discards deleted, cancelled, answered, expired, superseded, archived-family or revoked-inviter work. It uses three tries, a 30-second timeout and 10/60/180-second backoff.

Use a shared lock-capable cache for the per-administrator admission limit of 20 invitation attempts per hour across families. Contention fails closed. Invalid email inputs are rejected before admission; duplicate/member attempts consume an admitted attempt. Array cache is test-only, not a distributed rate limiter.

Continue supervising `php artisan queue:work --queue=mail,default --tries=3 --timeout=30`, with `retry_after` greater than the timeout. Configure the real public `APP_URL` and trusted proxies consistently so signed URLs remain valid. Reload long-running workers after an authorized release.

After-commit publication is **not** a transactional outbox: a crash between commit and enqueue can lose delivery. SMTP retry can duplicate mail, and cancellation cannot recall mail already handed to the transport; the link still revalidates on use. Production SMTP and exactly-once delivery are not certified here.

## Verification

Local qualification on PHP 8.5.12 / PCOV 1.0.12, Node 26.13.0 and the locked dependencies:

- Full suite: **287 passed, 1,208 assertions**, zero failures/errors/skips; **86.91%** line coverage (1,155/1,329 statements).
- Pint and Larastan level 6 passed with no new baselines/suppressions.
- Official portable Vite build passed; **11 tooling tests** passed, including the Octane watcher and real portable build. Native Vite on this VM exits 132 (`SIGILL`); the hosted native build remains a required exact-head CI gate.
- Real database-queue commit, rollback and one-shot worker delivery tests use the array mail transport, not SMTP.
- Real Chromium/HTTP browser smoke passed **20 cases**: the ten-case send/cancel/reissue/login/register/accept/decline/authorization matrix in each of English and Portuguese, with desktop/mobile screenshots, no page exceptions or HTTP 5xx, and no horizontal overflow in captured views. Synthetic accounts, a disposable SQLite database, real database sessions/queue and blocked external browser requests were used; preview listeners and databases were removed afterward.
- Production-mode cached configuration/routes/views booted against disposable SQLite. All **16 migrations** applied, the new migration rolled back and reapplied while preserving existing family memberships, and the complete chain reset/reapplied. SQL checks verified foreign keys and the unique invitation pair.
- Deterministic SQLite final-write interleavings prove the tested predicate boundary; they are not a multiprocess load test or PostgreSQL/MySQL locking qualification. The limiter has sequential and held-lock rejection tests, not a distributed load certification.

Run the normal repository gates (`composer lint`, `composer analyse`, `composer test:coverage`, `npm run build`, `npm run test:tooling`) and the focused `tests/Feature/Family/FamilyInvitation*Test.php` plus `tests/Integration/FamilyInvitationQueueTest.php`. Use `npm run build:portable` only for the documented older-CPU fallback. Test services must remain isolated as described in [LARAVEL_13_MODERNIZATION.md](LARAVEL_13_MODERNIZATION.md).

The existing broken default avatar, shell contrast and partially untranslated family labels remain unrelated UI debt. Local qualification is not merge/deployment approval; the PR records independent review and exact-head hosted CI results separately.
