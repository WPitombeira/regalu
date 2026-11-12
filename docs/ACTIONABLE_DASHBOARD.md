# Actionable dashboard and persistent inbox

This document describes the implemented dashboard/in-app notification slice. It does not imply implementation of the wider historical product vision.

## Dashboard

`GET /dashboard` mounts the authenticated Livewire dashboard behind `auth` and `auth.session`.

- Up to four active wishlists owned by the current user, and four active families they created or currently belong to, newest first.
- Up to four upcoming visible, unarchived, non-completed Secret Santa events. Dated events from today onward come first, ordered by date then ID; undated events follow.
- Up to four active assignment links, newest first, only while the current buyer and recipient remain accepted participants with a valid draw. Links target the existing protected assignment page, not a recipient or private wishlist identifier.
- Counts of currently valid pending family/event invitations addressed to the current account's uniquely matching normalized email. The reminder asks the user to check their email; it exposes no invited group/event names, codes or acceptance links.
- Five recent notifications, with empty-state links to existing list, family and event journeys.

Queries are recomputed on rendering. Dashboard models are not public Livewire state. Event cards expose only authorized metadata; assignment cards never contain recipient identities or wishlist contents. The dashboard does not show purchased item details or buyer identities.

## Persisted domain notices

| Trigger | Recipient | Stored content | Deduplication version |
| --- | --- | --- | --- |
| Family email invitation/reissue | Unique existing account matching the normalized invited email | Generic invitation reminder | Invitation generation |
| Secret Santa email invitation | Unique existing account matching the normalized invited email | Generic invitation reminder | Participant row |
| Draw completion | Each currently authorized buyer with a valid assignment | Generic assignment-ready reminder | Draw row |
| Scheduled reveal | Each currently authorized buyer with a valid assignment | Generic reveal reminder | Draw row, separate kind |
| Successful ordinary wishlist purchase | Current wishlist owner | Generic purchase reminder | Item row |

The persisted notification is inserted in the same database transaction as its domain mutation. A rollback leaves neither side committed. A nullable unique SHA-256 deduplication key combines kind, recipient, source identity and version; repeated producers do not create duplicate notices or reset read state. The existing after-commit email jobs remain separate. Assignment purchases retain their separate Secret Santa authorization path and do not invoke the ordinary purchase notice producer.

No backfill is performed for accounts created after an invitation, past purchases, draws or reveals. Missing or ambiguous normalized email matches receive no notice. A notification is not evidence that an email was delivered.

## Inbox, bell and action boundaries

`/notifications` paginates 15 rows. The bell and dashboard show five recent rows. Ordering is descending creation time with descending ID as a stable tie-breaker. Titles, messages, empty states and actions support English and Brazilian Portuguese. Known domain notices are rendered from canonical translated copy, not arbitrary stored text.

- Reading one notice or all notices updates only the current user's rows; foreign or nonexistent IDs cannot mark another user's data read.
- Opening a notice resolves the destination from current ownership, participation, lifecycle and source generation. Stored domain URLs are never redirect authority.
- Purchase notices can open only the current owner's unarchived wishlist. The destination retains its existing owner aggregate-only purchase view.
- Assignment notices open only active, unarchived, currently authorized assignments. Reveal notices additionally require a revealed state and a due reveal date; named events without a reveal date use their due event date, matching the scheduler. Anonymous events never use that fallback. Source draw replacement, revocation, completion or archival withdraws the shortcut.
- Invitation notices never have an acceptance URL. Signed, expiring, recipient-bound email confirmation remains required. Reading a reminder does not accept, join, resend or generate a capability.
- Expired, cancelled, reissued, accepted, declined, archived, deleted or no-longer-addressed invitations become unavailable. A user who already belongs is not counted as pending. Generic history may remain, but no stale action is restored.
- Legacy notices remain readable. Only exact root-relative `/dashboard`, `/notifications`, `/wishlists`, `/families` and `/secret-santa` destinations are permitted; external URLs, entity links, query strings and protocol-relative URLs are not followed.
- Internal source references and deduplication keys are excluded from rendered projection data. Unknown domain kinds fail closed as unavailable.

Marking read does not grant resource access. Protected destination routes independently reauthorize requests. Session invalidation also applies to real Livewire update requests. The bell refreshes on its existing 30-second poll; this slice does not add real-time cross-component broadcasting.

## Schema and operations

`2026_11_11_000001_add_domain_keys_to_notifications.php` adds nullable `kind` and nullable unique `deduplication_key` columns to the existing notifications table. Existing rows retain their content and null keys. Rollback removes the new columns/index without deleting existing notifications; application code and schema must be rolled back together. Run migrations before activating the new application revision.

No additional queue or external service is introduced. Continue running supervised workers on `mail,default` and the Laravel scheduler every minute as documented in [SECRET_SANTA_PRIVACY.md](SECRET_SANTA_PRIVACY.md) and [FAMILY_EMAIL_INVITATIONS.md](FAMILY_EMAIL_INVITATIONS.md). Production SMTP delivery and operational scheduler supervision are separate deployment checks, not established by array-mail or SQLite tests.

## Verification contract

- Feature tests in `tests/Feature/Dashboard/` and `tests/Feature/Notifications/` exercise current authorization, privacy projections, ownership isolation, stable limits/pagination, localization, rollback/deduplication, invitation lifecycle, schema up/down/up with legacy data, and HTTP Livewire session invalidation.
- Existing purchase mutation tests retain final-write revocation coverage. `tests/Integration/AssignmentQueueTest.php` combines transactional notice assertions with real database queue-worker delivery/rollback checks.
- `tests/Browser/dashboard_smoke.py` uses the real login, mounted Livewire components, ordinary purchase mutation, mark-read actions and pagination on an explicitly disposable SQLite database. It covers English/Portuguese at desktop/mobile widths, empty/active states, a generic owner notice, anonymous assignment navigation, stale revocation, foreign-user read isolation, keyboard bell behavior and viewport bounds. External browser requests and real mail are disabled.
- The browser harness shares lifecycle management with `event_history_smoke.py`; its original fixture remains the default. Both journeys are required CI steps with separate screenshot/result artifacts.
- Required CI also includes Pint, Larastan, dependency audits, native/portable frontend checks, the full PHP suite with at least 80% coverage, and disposable release cache/migrate/rollback/migrate smoke.

Run the browser command using an environment with the pinned Playwright version from CI, built assets, the project's required PHP runtime and a task-owned `TMPDIR` or `RUNNER_TEMP`:

```sh
python -B tests/Browser/dashboard_smoke.py --output coverage/dashboard-browser
```

A test definition or source review is not execution evidence. Exact-head results and any unavailable local gates belong in the PR handoff. SQLite controlled interleavings do not qualify parallel database locking or another production dialect.

## Explicitly outside this slice

Push/WhatsApp notifications, a full activity feed, notification preferences, email-provider changes, guided onboarding, invitation backfills, OAuth, URL price extraction and privacy/account-deletion journeys are not included.
