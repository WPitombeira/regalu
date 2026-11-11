# Secret Santa completion, archival and history

This closes the event-history portion of the product journey. Recipient secrecy and assignment delivery remain governed by [SECRET_SANTA_PRIVACY.md](SECRET_SANTA_PRIVACY.md). These are explicit organizer actions, not automatic expiry or data deletion.

## Lifecycle contract

| Action | Current-state requirements | Result |
|---|---|---|
| Complete | Current organizer; unarchived; event date unset or due; named event in `DRAWS_COMPLETE` or `REVEALED`, or anonymous event in `REVEALED` with its non-null reveal date due | Status becomes `COMPLETED`; event wishlist content and purchases close |
| Archive | Current organizer; unarchived; `COMPLETED` | `is_archived` becomes true; normal detail and assignment access close; redirect to past events |

Date eligibility uses the application's `APP_TIMEZONE`. A future event cannot be completed early, and an anonymous event cannot be completed before its scheduled reveal has occurred. There is no reopen/unarchive action. Both actions require confirmation in the UI and recheck authorization and lifecycle predicates in the final conditional database update; a stale page cannot authorize a changed organizer or lifecycle state.

Completion does not erase draws, participants, purchases or stored consent. Until archival, accepted participants may remove their saved wishlist consent, but cannot add or replace it. The final consent update checks current participant identity/status, event/archive state and, when sharing, current wishlist ownership/activity. After archival, normal event actions are denied, including consent edits.

Completed, unarchived events retain each accepted buyer's own recipient identity summary under the existing visibility rules. They no longer return wishlist contents or accept event purchases, even from an earlier action reference. Archived events deny assignment access entirely. Neither transition revokes independent ordinary wishlist/family grants or recalls information already viewed.

## Private history

- `/secret-santa` defaults to **Current events**. **Past events** (`?period=past`) lists completed or archived events, including older archived records regardless of status.
- A current organizer or currently accepted participant can access `/secret-santa/{event}/history`. Pending/declined/removed participants and unrelated users cannot. Normal authentication and session revocation apply to HTTP and Livewire requests.
- History selects only event ID, name, description, type, status/archive flag, event date, budgets and accepted-participant count. It does not load invitations, event codes, roster identities, pairings, wishlist IDs or gift contents. Host-authored names/descriptions are still visible metadata, not anonymized text.
- The history component uses a locked event ID and refreshes the authorization-scoped projection on every render. Losing membership/ownership closes access on the next request; a previously loaded component is not a continuing grant.
- Listings use stable ordering and twelve records per page. Changing the period resets pagination; an emptied later page retains backward navigation.
- Completed, unarchived history links back to normal detail so an organizer can archive and a participant can remove consent. Archived history does not reopen normal detail.

The existing **organizer-only read-only participant/exclusion management views** remain a separate authorization surface, including for archived events. They do not grant other participants' pairings. History does not link to or embed those views, and its metadata-only contract must not be implemented by weakening the normal event `view` policy.

## Queue and operations

Assignment jobs resolve current state at execution and skip completed/archived events. Already-sent email cannot be recalled; after-commit dispatch is not a transactional outbox or exactly-once delivery. This change adds no completion/archive email, persistent notification, scheduler job, migration or production dependency. Keep the existing reveal scheduler and supervised `mail,default` workers; reload workers after a normal deployment of changed job code.

Archival retains database records and is **not** an LGPD deletion mechanism or a fixed retention policy. Bulk/automatic completion, automatic archival, recurring events, history export, reopening and data erasure remain separate scope. No merge, production deployment, SMTP qualification or scheduler activation is implied by source/tests.

## Verification

Focused PHP entrypoints:

```bash
php artisan test tests/Feature/AmigoSecreto/EventLifecycleTest.php
php artisan test tests/Feature/AmigoSecreto/EventLifecycleUiTest.php
php artisan test tests/Feature/AmigoSecreto/EventHistoryTest.php
php artisan test tests/Feature/AmigoSecreto/EventHistoryBoundaryTest.php
php artisan test tests/Feature/AmigoSecreto/EventWishlistTest.php
php artisan test tests/Feature/AmigoSecreto/AssignmentWishlistTest.php
php artisan test tests/Integration/AssignmentQueueTest.php
```

The feature lane covers due-date/state matrices, organizer boundaries, projection secrecy, stale HTTP sessions/permissions and deterministic revocations immediately before real SQLite updates. The integration lane drains an obsolete assignment through a real database queue worker with array mail. These are not multiprocess stress tests, SMTP delivery tests or qualification of other database engines.

CI also runs `tests/Browser/event_history_smoke.py --output coverage/browser` with a pinned isolated Playwright runtime. It uses the real Laravel/Livewire application, native built assets, synthetic host/participant accounts, an independently migrated disposable SQLite database for each case and fresh browser contexts. English and Portuguese each run at desktop and narrow mobile widths. Journeys exercise confirmation cancellation/acceptance, persisted completion, closed wishlist access, consent removal, metadata-only history, persisted archival, pagination/filter reset and archived route denial. Screenshots, viewport overflow checks, JavaScript errors and the exact source commit are retained in the `event-history-browser` artifact. Third-party browser traffic is blocked; no real mail or production data is used.

For an authorized local run, first build assets, install the workflow's pinned Playwright version in a task-owned virtual environment/browser cache, set `TMPDIR` to task-owned scratch storage, then run that script with an output directory outside the repository. It refuses non-owned fixture databases and cleans up its PHP server and temporary databases. Honor shared-host resource guards. Final release readiness additionally requires the whole PHP suite, enforced coverage, Pint/Larastan, audits, native/portable build and release-cache/migration smoke gates on the exact publication head; the PR records those results rather than inferring them from focused tests.
