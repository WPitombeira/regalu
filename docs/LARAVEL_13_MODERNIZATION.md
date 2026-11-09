# Laravel 13 application modernization

This is the application/CI follow-up to the dependency upgrade in [DEPENDENCY_UPGRADE.md](DEPENDENCY_UPGRADE.md). The candidate builds on `a5761816c94ee8fd8b032688874e5a103fa0af23` (Laravel 13.40, Livewire 4.4, PHP 8.5.12). It does not represent a deployment or a successful remote GitHub run.

## Adopted features and decisions

| Area | Implementation | Regression evidence |
|---|---|---|
| Laravel 13 authorization attributes | Controller `#[Authorize]` declarations, auto-discovered family/event/wishlist policies, fresh authorization at Livewire action/render boundaries | Family/event resource authorization and wishlist authorization suites; revoked membership, cross-family targets, non-organizers, changed ownership and expired sessions |
| Laravel 13 queue routing and job attributes | `Queue::route` routes `SendEventInvitation` to `mail`; `#[Tries(3)]`, `#[Backoff(10, 60, 180)]`, `#[Timeout(30)]`; scalar participant ID, current-state checks, `afterCommit()` | Real database-queue payload and `queue:work --once`; rollback publishes nothing; revoked/archived/finalized invitations do not send |
| Laravel 13 request forgery protection | Preserve the framework's origin-aware middleware and token fallback; POST-only logout with full session invalidation | Tests exercise the real middleware with cross-site, same-origin and valid-token requests |
| Serialization hardening | JSON sessions and `cache.serializable_classes = false`; preserve existing cookie/cache prefixes | Actual session-handler bytes and cache round-trips, including rejected arbitrary object rehydration |
| Eloquent development safeguards | Prevent lazy loading and silently discarded attributes outside production | Framework-default assertions plus full suite |
| Bounded listings | `simplePaginate(12)` for events/wishlists, `simplePaginate(20)` for items; unique ordering tie-breaker; filter resets | Pagination boundary and filter tests; no per-event participant SELECTs; one shared-family visibility query per detail rerender |
| Safe external metadata | Laravel HTTP client with validated public DNS answers, cURL address pinning, no redirects/proxies, connection/transfer timeouts, oversized-body rejection, sanitized images, hashed six-hour cache | HTTP fake tests **and real loopback cURL tests** for pinning, proxy bypass, redirects and unknown-length transfer aborts |

These choices follow the [Laravel 13 upgrade guide](https://laravel.com/docs/13.x/upgrade) and [release notes](https://laravel.com/docs/13.x/releases). Not every new framework feature belongs in this application:

- AI SDK, vector search and JSON:API resources are deferred: Regalu currently has no AI workload, vector store, or public API that justifies them.
- Explicit relationship loading/counts are preferable here to globally enabling automatic eager loading and concealing query costs.
- Durable invitation work uses the database queue, not deferred execution after an HTTP response. A worker is required.
- Existing relation declarations and conventional validation remain where attribute conversion would only create churn.

## Existing flows repaired

- HTTP and Livewire login share an email/IP attempt limit; login no longer validates registration fields. The remember checkbox is interpreted as a boolean.
- Registration binds the real name property. Login, forgotten-password and authenticated navigation now reach implemented routes.
- Logout is CSRF-protected POST, clears the session and rotates the CSRF token. Authenticated routes, including shareable wishlist/event URLs, validate the session's password hash. Laravel's core `AuthenticateSession` is explicitly persistent on Livewire updates; real HTTP snapshot/update tests reject stale sessions before mutations. Share links retain their URLs and redirect guests through login; they do **not** create anonymous wishlist access.
- Settings routes mount the implemented profile/password component. Password changes rotate the remember token and advance only the current session's password fingerprint, preserving that session while rejecting older ones. Password-reset tests cover valid, invalid, malformed and replayed tokens.
- Family administration cannot target another family's membership. Event exclusions require accepted event participants; finalized/archived events reject the relevant mutation paths. Draw views only retrieve the current viewer's assignment.
- Wishlist buying uses a conditional update so a stale second buyer cannot overwrite the first buyer. Item projections exclude buyer IDs. Shared-family visibility uses an SQL existence query rather than loading both users' membership collections.
- Metadata-selected image state is locked against client tampering before preview rendering, and bounded HTTP(S) URL validation also runs before saving. Positive tests cover fetching, saving and clearing the locked preview.
- Event join tests cover ordinary joins, linking the existing email invitation without duplication, and preserving unrelated event invitations.
- Notification tests now drive actual Livewire components and verify ownership, pagination and logout behavior.
- Newsletter rollback drops the correct table. The unused malformed PSR-4 user-card class was removed; the anonymous Blade component remains.
- Queue acknowledgement copy says "queued", not "sent". Draw completion no longer claims assignment emails were delivered.
- Repository-wide Pint formatting and explicit return/relation types remove the existing formatting/Larastan debt without adding an analysis baseline or suppressions.

## Verification

Measured with PHP 8.5.12 / PCOV 1.0.12 and the same `app/` coverage source inclusion:

| Gate | Before this application pass | Candidate |
|---|---:|---:|
| PHP tests | 66 passed, 6 TODO | **190 passed, 616 assertions**, no TODO/skips/errors/failures |
| Line coverage | 52.2% (435/834 statements) | **83.8% (981/1170 statements)** |
| Larastan level 6 | 78 errors | **0 errors** |
| Pint | Existing failures | **Pass**, repository-wide |
| Node tooling | Existing build/watcher tests | **9 passed** including CI contracts |

Executed gates also include strict Composer validation, optimized strict-PSR autoload generation, platform requirements, Composer/npm security audits (no reported vulnerabilities), the actual portable Vite build, and actionlint. Actionlint 1.7.12 requires ignoring its outdated `ubuntu-26.04` label warning; no other lint errors were suppressed.

The release smoke built production-mode configuration, route and Blade caches against a fresh disposable SQLite file, migrated all 15 migrations, rolled them back, and reapplied them. Generated configuration/route/view caches were cleared afterward.

A real Chromium/Playwright smoke used synthetic accounts, disposable SQLite, database sessions, and blocked external network access. It passed registration, login/POST logout, settings, persisted wishlist creation, authenticated list pages, and mobile wishlist rendering: nine checks, no JavaScript exceptions or HTTP 5xx responses. This is not SMTP-delivery evidence or an exhaustive accessibility test. Existing low-contrast branding/theme tokens and missing default-avatar artwork remain UI debt.

### Local commands

Use the qualified runtime and install dependencies from the checked-in locks. A local `.env.testing`, if absent, should be comment-only; tests force their SQLite/array/sync configuration in `phpunit.xml`. Never copy production environment files or use production databases for tests.

```bash
composer validate --strict
composer check-platform-reqs
composer audit --locked
npm audit
npm run build                 # native CI path
# npm run build:portable      # older virtual CPUs with the native SIGILL issue
npm run test:tooling
composer lint
composer analyse
composer test
composer test:coverage       # PCOV/Xdebug required; enforces at least 80%
```

Reports go to ignored `coverage/clover.xml` and `coverage/junit.xml`. `composer check` runs formatting, static analysis and tests. CI additionally enforces coverage, audits, builds and release smoke. Warnings, risky/deprecated tests and an empty suite fail via PHPUnit's strict issue settings.

## GitHub Actions

`.github/workflows/laravel.yml` now has separate PHP-quality, asset and test jobs. Quality runs independently; the test job consumes the native asset artifact instead of rebuilding npm dependencies. It includes:

- Push/PR checks for `master` and manual dispatch; per-ref superseded-run cancellation and job timeouts.
- Read-only repository permission, no checkout credential persistence, full-SHA action pins, no `pull_request_target`, OIDC or application secrets.
- Lockfile installs, Composer download caching (not stale `vendor` trees), npm caching and dependency audits.
- Native frontend build plus portable-build/Octane-watcher tests.
- PCOV coverage floor of 80%, Clover/JUnit uploads even after failure, isolated test environment and ephemeral application keys.
- Disposable release-mode cache/migration/rollback/reapply smoke with unconditional cache cleanup.
- Weekly Dependabot updates for Composer, npm and Actions, grouping compatible minor/patch updates.

**Remote status:** the workflow has been linted and its local gates exercised, but has not been pushed or run on GitHub as part of this change. Native Rolldown raises SIGILL on this VM; local asset evidence uses the official WASM path. The native build remains a required GitHub gate, not a claimed local pass.

## Rollout requirements and limits

1. **Session compatibility:** changing from PHP-serialized to JSON sessions can sign existing users out. Plan a deliberate reauthentication window. A temporary `SESSION_SERIALIZATION=php` preserves old behavior during a coordinated rollout, but means the JSON hardening is not active yet. Do not blindly rename cookies/cache namespaces or rotate the production application key.
2. **Queue worker:** use an asynchronous connection such as the existing database default and supervise `php artisan queue:work --queue=mail,default --tries=3 --timeout=30`. Keep `retry_after` greater than the timeout (the current database default is 90 seconds). Reload long-running queue/Octane workers after an authorized rollout and monitor failed jobs. With `QUEUE_CONNECTION=sync`, delivery remains synchronous by explicit configuration.
3. **Delivery semantics:** `afterCommit()` avoids publishing rolled-back invitations; it is not a transactional outbox. A crash after database commit but before queue publication can lose delivery. SMTP retries are at-least-once and may duplicate mail. Exactly-once delivery and production SMTP are not proven.
4. **Concurrency:** the conditional gift update is regression-tested with stale model instances, not a multiprocess load test. Participant/event and invitation-email pairs still lack database uniqueness constraints; concurrent joins/invites require a separate schema/data reconciliation and race qualification before high-concurrency use. SQLite tests do not qualify MySQL/PostgreSQL lock behavior.
5. **Network boundary:** PHP DNS resolution uses the host resolver; HTTP connect/transfer timeouts do not impose a separate DNS deadline. Connection pinning validates server-side destination addresses; outbound network policy is still recommended. Parsed responses are capped at 512 KiB; transfer cancellation can overshoot that threshold by transport buffering. Proxy-dependent deployments need an explicitly reviewed egress design rather than silently restoring ambient proxy use. Images still load from remote origins in the viewer's browser; locked metadata state is not an image proxy or a guarantee against later remote redirects/DNS changes.
6. **Product gaps:** newsletter/contact wiring, OAuth buttons, scheduled reveal/assignment mail, domain notification wiring and the dashboard remain incomplete. The legacy deployment script and pre-existing mobile/visual issues are not certified by these tests. This modernization is not a blanket production-readiness approval.

The historical findings in [PROJECT_MAP.md](PROJECT_MAP.md) describe the pre-modernization baseline. The repaired items above supersede those entries; unimplemented product workflows remain explicit follow-ups.
