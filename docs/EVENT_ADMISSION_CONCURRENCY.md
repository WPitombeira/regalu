# Secret Santa roster concurrency

This extends [event join boundaries](EVENT_JOIN_BOUNDARIES.md). It changes roster serialization and schema constraints, not authentication, recipient privacy, SMTP delivery, or the meaning of a valid event code.

## Transaction contract

`EventRoster::locked()` starts one transaction, obtains the event's write lock before reading membership, and reloads the event through a locking read. The initial no-op UPDATE also obtains SQLite's writer lock, where `SELECT FOR UPDATE` alone would not. An unchanged MySQL row can report zero affected rows; that is not a missing event.

The following application actions use this event-first order and reauthorize the freshly locked event:

- code admission and invitation acceptance (`JoinEvent`);
- email invitation and participant removal (`ManageParticipants`);
- draw execution (`ExecuteDraw`);
- exclusion addition/removal (`ManageExclusions`);
- participant wishlist consent (`EventDetail::saveWishlist`).

Roster and exclusion reads inside these transactions are locking/current reads, including when a caller already established a MySQL REPEATABLE READ snapshot. Event creation and the organizer's accepted participant are now one transaction: failure of the second insert leaves neither row. Existing event lifecycle updates acquire the same event row through their conditional UPDATE. This protocol does not fence arbitrary SQL, future writers, user-account changes, or external delivery.

Duplicate joins return the existing participant without another insert or success notification. Duplicate email invitations preserve the draft, return the existing field error, and create no invitation job or persistent notice. Invitation lookup compares normalized invitation email and the email of an already-associated account, covering join-before-invite as well as invite-before-join. Drawing and roster/exclusion mutations serialize: the winning operation determines the roster/exclusions used, and edits after a completed draw are refused by the freshly checked policy. Repeated draw execution does not create another set of assignments.

There is **one framework transaction attempt**, not message-based automatic retry. On SQLite, a competing writer may receive `SQLITE_BUSY`; callers must explicitly retry the user action after contention clears. The native test commands that retry explicitly and does not claim transparent application retry. The existing draw error presentation also catches runtime/database exceptions and reports draw failure; this slice does not redesign that error UI.

## Database identity and populated upgrades

Migration `2026_11_14_000001_enforce_event_participant_identity.php` adds:

- unique `(event_id, user_id)` for non-null accounts;
- unique `(event_id, LOWER(TRIM(invite_email)))` for non-null invitation addresses.

These apply across participant statuses, not only accepted/invited rows. Null values remain distinct, allowing several email-only invitations or code-only members. Email identity follows the database's existing email collation and `LOWER/TRIM` behavior; this is not a new Unicode/email canonicalization policy. MySQL uses a `VIRTUAL INVISIBLE` generated column with the original email charset/collation, so wildcard reads do not add it to old model projections. Both unique indexes and that column are installed in one ALTER. SQLite/PostgreSQL use expression indexes inside a transaction.

Historical duplicates are **not deleted or silently merged**. Before DDL, aggregate probes refuse account or normalized-email collisions with an explicit preservation-plan error. A concurrent collision at index creation still makes the DDL fail. On MySQL 8.0, the single ALTER is atomic. A production rollout requires an operator-owned writer pause, current read-only collision preflight, and a private verified backup. Any collision needs an approved preserve/merge/quarantine policy before retrying; this PR does not authorize a data repair or a deployment.

Downgrade removes only these guards, preserving participant rows. InnoDB may have dropped its implicit event foreign-key index after the new unique indexes covered it; downgrade restores a covering event index in the same ALTER when needed before dropping the guards. Native populated down/up and rejection probes verify row preservation, both unique rejection paths, no partially installed guards after preflight refusal, and MySQL invisibility/collation. This is a synthetic populated rehearsal, not a production-data audit or full production restore drill.

## Qualification

Focused Pest regressions cover identity constraints, populated migration refusal/round trip, invitation deduplication, event-first transaction ordering, final-write boundary compatibility, and failed organizer admission rollback.

The existing bounded Linux/amd64 MySQL operator gains an explicit admission mode:

```sh
python3 -B tests/Tooling/event_join_mysql_test.py
python3 -B tests/Tooling/event_join_mysql.py --admission --output <new-evidence-directory>
```

Use the digest-pinned image and prerequisites in [EVENT_JOIN_BOUNDARIES.md](EVENT_JOIN_BOUNDARIES.md). Admission mode requires `pdo_mysql` and `pdo_sqlite`. It runs the full migration chain on disposable MySQL 8.0.46 and a private file-backed SQLite database, then uses independent PHP processes with committed fixtures and the real Livewire action methods. It does not replace the separate HTTP/browser middleware tests.

The matrix has 16 shared scenarios: same account, normalized email, repeated draw, both invitation/join orderings, both draw/join orderings, both draw/removal orderings, both draw/exclusion-add orderings, both draw/exclusion-removal orderings, rollback followed by invitation, and both consent/removal orderings. MySQL adds two pre-established REPEATABLE READ snapshot cases, for joins and invitations.

MySQL contention is proved through `performance_schema.data_lock_waits`, joined to the actual workers' connection IDs and event table. SQLite contention is proved by a native PDO busy error followed by an explicit retry command after the first worker commits. Fixed sleeps are not accepted as contention evidence. Every case checks native worker exits, exact roster/draw/job/notice counts, expected refusal/idempotency outcomes and, for draws, accepted-member bijection, no self-assignment and respected exclusions. Queue assertions use the real database queue but do not execute SMTP or prove crash-safe/exactly-once delivery.

Receipts require the complete ordered driver/case matrix, exact boolean/integer types, migration assertions and known barrier types. Source hashes cover application PHP, bootstrap/config/routes, migrations, views/translations, Composer manifests and probe files, checked again after execution. These checks detect ordinary drift; they are not an all-writer filesystem fence. The runner retains its byte/deadline bounds, ownership/readback cleanup and unchanged protected-container/volume inventory requirements. Synthetic orchestration tests are labeled separately from native database results.

CI requires `--admission` in the existing MySQL step, retains `event-join-mysql` receipts, and still runs the complete event-join browser matrix, PHP quality/coverage, native/portable assets and release migration/cache smoke. Native qualification here is **MySQL 8.0.46 and SQLite only**; PostgreSQL/MariaDB code branches are not certified by these runs. Full-suite MySQL behavior, load capacity, deployment data and operational rollback remain distinct checks. PR publication must bind the reviewed tree, exact hosted head, CI results and downloaded artifacts; no merge or deployment is included.
