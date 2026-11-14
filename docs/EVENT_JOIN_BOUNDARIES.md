# Secret Santa join boundaries

## Contract

`JoinEvent::join()` keeps authenticated code admission separate from email invitation identity. A valid current event code can admit an account without an email invitation. When the dashboard offers this account a pending invitation, joining reuses that exact row rather than creating a second participant.

- Invitation eligibility comes from `NotificationInbox::eventInvitationsFor()`: normalized email matching, a unique matching account, a null participant user, `INVITED` state, and current event eligibility. If several eligible invitations exist, the lowest ID is selected deterministically; this change does not repair historical duplicates.
- Both final write paths require the same event ID and current submitted code, an unarchived event that is not `DRAWS_COMPLETE`, `REVEALED` or `COMPLETED`, an existing actor, and no participant already associated with that actor in the event.
- Invitation acceptance is a conditional update of the selected ID through the complete eligibility query, materialized as a `DISTINCT` derived table so MySQL does not merge reads back into the UPDATE target. All eligibility checks still execute in the final write. Recipient/email ambiguity, participant rebinding, status changes or deletion before the update deny the action without retrying an unconditional write or creating a replacement row.
- Code-only admission uses `INSERT ... SELECT` over the current eligible event. An unrelated, declined, withdrawn or ambiguous email invitation remains unchanged; knowing the valid event code is a separate admission capability.
- A late refusal returns 403 before the success notification or redirect. Existing entry validation/policy checks and the early redirect for an already-associated participant are unchanged.
- Both paths remain behind the existing `auth` and `auth.session` route/persistent Livewire middleware. No new email, queue, schema, or credential flow is introduced.

The successful destination header now stacks its title/status and event-type badge on narrow screens, while retaining a row on desktop. The real 390px journey exposed a type badge extending past the viewport with the ordinary title `Fixture DIRECTCODE01`; the correction preserves that fixture and the strict overflow assertion.

## Verification boundaries

### PHP

`JoinEventBoundaryTest.php` covers normalized invitation reuse, deterministic lowest-ID selection with an unchanged duplicate invitation, and single-hit, real-database changes immediately before the final participant statement: event closure/archive/code rotation, actor deletion, a newly associated participant, invitation recipient/identity changes, and unavailable invitations with independent code admission. Refused writes preserve the affected rows and dispatch no success notification. Positive cases prevent an unconditional-refusal implementation from passing.

`JoinEventHttpTest.php` obtains the actual route's signed Livewire snapshot and posts it to the installed update endpoint for current, revoked and closed states, with and without an invitation. The six cases verify the real persistent-middleware boundary; the JSON request's revoked-session contract is 401. This is distinct from the browser fetch's HTTP 302 to `/login`.

`JoinEventPresentationTest.php` checks the responsive header wiring through actual rendered HTML. It is markup evidence; the browser supplies the layout evidence. Existing join and resource-authorization tests remain in the focused gate.

Controlled statement-boundary SQLite tests **do not prove parallel-process serialization or other-dialect boundary behavior**. There is no new unique event/user constraint or all-writer locking protocol. Conditional insertion bypasses model creation hooks; the current participant model has no creation observer to preserve. These limitations must be reevaluated before adding a new database dialect or participant creation side effect.

### Native MySQL compatibility

The source review exposed a real MySQL error 1093 in invitation acceptance: the UPDATE read its target table through eligibility subqueries. A disposable MySQL 8.0.46 probe reproduced that error while direct code admission passed. The same probe passed both paths after adding the derived-table barrier. MySQL documents [DISTINCT as preventing derived-table merging](https://dev.mysql.com/doc/refman/8.4/en/derived-table-optimization.html). No global optimizer or database settings are changed by the application.

The permanent regression is:

```sh
python3 -B tests/Tooling/event_join_mysql_test.py
docker pull --platform linux/amd64 mysql@sha256:0845b57a4927c2e2e442b0c3c154ebf2bdede7d6e1cb3b5a4b3e8bc1d0e69e22
python3 -B tests/Tooling/event_join_mysql.py --output <new-evidence-directory>
```

This Linux/amd64 gate requires Docker, the application's PHP/development dependencies and `pdo_mysql`. A task-local PHP runtime may supply `--extension-dir <directory>` containing its matching `mysqlnd.so` and `pdo_mysql.so`. It checks 3 GiB available RAM/disk and load at most 6 before Docker inventory. The uniquely labeled container has no network or published port, 1 GiB memory, one CPU, 256 PIDs, bounded logs and a 512 MiB disposable data tmpfs. Only an owned Unix-socket directory is mounted; no application database, environment file or uploads are used. PHP boots the real application with an environment allowlist, asserts isolation before migrations, then runs real Livewire invitation and code actions with persisted-row assertions. Its result pins source/probe/runner hashes, driver, server version and migration count.

Subprocess output and deadlines are bounded. Container identity/isolation are checked before start; teardown revalidates ownership and reads back exact absence after one removal. Lost acknowledgements remain failed even if cleanup is proven, and unproven cleanup retains the fixture path for reconciliation rather than retrying mutations. The separate runner tests exercise actual child-process limits/reaping and synthetic Docker/PHP-boundary failure cases; those doubles are not native database evidence.

This is MySQL syntax and successful-action compatibility evidence, **not** a whole-suite MySQL qualification, native concurrent-revocation proof, MariaDB/PostgreSQL qualification or middleware test. The real HTTP/browser gates below cover the session middleware separately.

### Real browser

Run after building assets:

```sh
python -B tests/Browser/event_join_smoke.py --output <new-evidence-directory>
python -B tests/Browser/event_join_report_test.py --report <new-evidence-directory>/results.json
```

The complete matrix is English and Brazilian Portuguese at 390px and 1280px, 844px tall, dark scheme, JavaScript enabled. Every case executes real login, normalized invitation acceptance with exact row reuse, removal from the dashboard's pending invitation projection, and lowercase code admission. It then verifies rejection after synthetic lifecycle closure and synthetic password-hash rotation, unchanged participant persistence, and rejection of a subsequent protected GET. The two synthetic database changes are fixture fault injection, **not** evidence of organizer actions or the profile password-change UI.

Each case retains eight screenshots and nonempty geometry measurements. The harness checks matching Livewire action responses, exact destination URLs and rendered headings, persisted database values, viewport/control bounds, and absence of page errors. It waits for the framework's revoked-session redirect to finish before starting the separate protected GET. The browser does not claim light-mode/no-JavaScript, contrast, all-viewports, whole-page localization, or whole-site accessibility qualification.

Fixtures use run-owned disposable SQLite/storage/session/public/cache paths, an environment allowlist, disabled external requests, and array mail/synchronous queue settings. Both PHP seed and HTTP server pin `variables_order=EGPCS`; a real HTTP probe verifies storage roots. Processes and temporary directories are cleaned up. Generated passwords/keys are ephemeral and are not recorded in reports; password fields are masked in screenshots.

Retained Low limitation: selecting a loopback port and then launching the HTTP fixture has a bind/close/start handoff window. A competing local bind can fail this harness; it is not an application authorization defect.

Reports require the exact case/checkpoint/check set, source and asset hashes, checkout metadata, successful HTTP contracts, screenshot hashes and bounded PNG decoding. Negative probes reject duplicate/incomplete cases, changed source/checkout metadata, empty measurements, missing/tampered captures and relaxed security results. Separate non-secret fixtures prove PNG corruption/dimension/truncation refusal and asset path traversal/absolute-path/outward-symlink confinement. The report records dirty `HEAD` only as a checkout label: pre-commit qualification must separately bind the input hashes to the frozen candidate tree without rewriting that history.

### Publication gates

The workflow runs the native MySQL probe and the complete browser matrix/report validator with failure propagation, uploading `event-join-mysql` receipts and `event-join-browser` JSON/PNG evidence. The tooling contract requires the commands, `-B`, pinned database image and fail-closed artifact absence. Existing PHP quality/audits, native/portable assets, coverage, release cache/migration/rollback, event-history and dashboard gates remain intact.

Local results do not establish hosted exact-head CI, required branch-protection configuration, or deployment. Publication requires an independent review of the frozen native tree, matching committed bytes, and verification of the exact published head's hosted results and artifacts. No merge or deployment is part of this slice.
