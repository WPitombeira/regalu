# Public newsletter signup

This slice repairs the newsletter form mounted on the public homepage. It is based on `master` at `e561bc0ed7488cf60e330c4a38caac17a669e770`; it does not include merging or deployment. It supersedes the newsletter-wiring gap in the historical modernization report, not the separate contact-form or marketing-delivery gaps.

## Behavior and boundaries

- The actual `home.index` Livewire component handles signup. The legacy `home` alias inherits the same implementation, so it cannot bypass validation or admission. The unused `NewsletterController::register()` bypass is removed.
- Signup requires an explicitly checked product-update consent checkbox and a nonempty RFC-valid email address of at most 254 characters. It does not validate unrelated contact-form fields.
- New addresses are trimmed and lowercased. A write-connection lookup preserves legacy mixed-case records. Existing active **and inactive** subscriptions, including timestamps, remain unchanged; signup never reactivates an inactive subscription.
- The existing email uniqueness constraint plus Eloquent `createOrFirst()` handles a canonical-address insert conflict without rewriting the winning record. No schema or dependency changes are needed. This does not establish cross-dialect case-insensitive uniqueness for arbitrary legacy/import writers.
- New, active-existing and inactive-existing requests receive the same generic receipt. No database identifier, existing-state flag or email-delivery claim is returned. This is response-copy parity, not a constant-time anti-enumeration guarantee.
- The receipt is server-controlled and locked against client updates. Editing either signup field or attempting another submission clears stale success. Accepted requests reset the address and consent; rejected requests retain input and render localized field/form errors without database or cache details.
- No mail, campaign job or external subscription-provider call is made. The form records interest only. It does not verify email ownership, provide double opt-in or unsubscribe management, record a versioned legal-consent ledger, or establish LGPD/GDPR compliance. Those capabilities and an actual privacy-policy page remain follow-ups before marketing delivery. The former placeholder policy link is not presented as a working policy.
- The separate contact form still has no delivery backend. Both homepage aliases now handle its existing `registerFeedback` action with a localized unavailable alert, retain the draft, and do not emit a success notification or send/store the message. This closes the missing-action regression found during review without restoring the legacy fake newsletter-success notification or claiming contact delivery.

## Admission and operations

`NewsletterSignupLimiter` reserves at most five attempts per source IP in a 60-second window, including invalid submissions. It uses the configured limiter cache (or default cache), a SHA-256-derived source key and a short shared lock around the counter reservation. A missing source address uses one fallback bucket. Cache resolution/operation failures and lock contention fail closed, before newsletter persistence, with a generic retry message.

Production requires a shared lock-capable cache across application workers, such as the application's existing database store. Process-local array cache is suitable only for component tests. Proxy trust must reflect the actual deployment so `request()->ip()` represents the intended source; neither distributed bot prevention nor a global traffic ceiling is claimed. Users sharing a source IP also share the small budget. A hashed IP cache key is pseudonymous, not anonymized data.

No queue or scheduler change is required. An authorized rollout would need the usual application/Octane worker reload to pick up source changes; this PR does not perform that operation.

## Interface

English and Brazilian Portuguese catalogs provide email labels, purpose/consent, independent validation messages, recoverable failures and the same generic receipt. Inputs have associated labels, required/autocomplete/length semantics and error descriptions; failures use alerts and success uses a polite live status. The submit button is disabled during its own request. The newsletter section uses wrapping mobile layout and touch-sized controls without changing the rest of the homepage.

The Portuguese homepage previously supplied an array where the feature subheadline requires text, causing an HTTP 500 before reaching signup. The scalar-catalog repair is necessary on this master-based slice; PR #20 contains the same independent repair, not an additional behavioral dependency.

## Regression coverage

- Actual public GET/mounted alias, explicit consent, address validation independent of contact fields, legacy alias parity, normalized retries, active/inactive/mixed-case preservation and unchanged timestamps.
- A controlled single-connection SQLite interleaving inserts a subscription **after** the preflight SELECT and **before** `createOrFirst()` opens its nested savepoint. It proves the actual uniqueness-conflict recovery preserves the winning row; it is not multiprocess concurrency or native MySQL/MariaDB/PostgreSQL qualification. Injecting inside that savepoint would roll the fixture back with the failed write and is not a valid competing-commit simulation.
- Attempt exhaustion/expiry, invalid-attempt counting, separate source budgets, missing-address fallback, cache failure and held-lock denial, with valid positive twins.
- Generic receipt and no outgoing mail, stale receipt reset, client-forgery denial, real SQLite-trigger persistence failure, localized rendering and independent expected-copy assertions for both locales.
- Contact submission through both aliases in both locales: successful HTTP handling with an explicit unavailable error, retained draft, no newsletter mutation, notification, outgoing mail or queued job. The four regression cases first failed with the missing-action exception and then passed after the bounded correction.

The browser smoke uses the real homepage, real Livewire HTTP requests and a fresh disposable SQLite database. Only its newsletter fixture uses persistent database cache; existing event/dashboard fixtures keep their array-cache behavior. The seed refuses non-testing/cached configuration, unexpected database/cache/session targets and external mail/queue services before migration. External browser requests are blocked, and the owned PHP server is terminated and its listener checked on exit.

The required browser matrix is EN/PT × desktop 1280×900/mobile 390×844. Each case checks native validation, deliberately bypassed native validation for server-side consent/address rejection, keyboard consent and submission, stored canonical address, duplicate and inactive-row preservation, and a sixth request from a fresh anonymous session sharing the source-IP budget. Seven per-case newsletter checkpoints retain screenshots and immediate control/document geometry assertions. A separate contact submission checks the localized unavailable alert, retained draft and unchanged newsletter/cache state, with its own screenshot. Reports bind source and asset identities and require no JavaScript exceptions. These assertions are planned runtime gates until the actual smoke passes; PHP rendering tests alone do not certify them.

## Qualification at the source-review checkpoint

Executed locally on the isolated PHP 8.5.12 / Node 26.13.0 / npm 12.2.2 toolchain:

- Focused newsletter suites plus the existing homepage suite: **51 tests, 463 assertions**, no failures or warnings. The contact correction alone passed **4 tests, 68 assertions**, after all four cases reproduced the missing-action failure.
- Scoped Pint and tracked whitespace checks: passed.
- Strict Composer validation, installed platform requirements and locked Composer security audit: passed, no reported advisories. The full npm audit also passed with zero reported vulnerabilities.
- Portable production Vite build: passed. Native `npm run build` failed with exit 132/SIGILL on this older virtual CPU; this is not a native-build pass.

The shared-host load guard denied browser, full coverage and static-analysis commands before execution. They remain **NOT RUN locally**, not failed test assertions or inherited passes. Full-suite/80% coverage, Larastan, native build, tooling, release smoke and actual browser/visual qualification remain required in hosted CI. The parent implementer owns those pending exact-head gates and any corrections; source-review approval alone does not make the PR ready.

Reproduce the bounded PHP gate after configuring the repository's isolated test environment:

```sh
php vendor/bin/pest tests/Feature/NewsletterSignupTest.php tests/Feature/NewsletterRateLimitTest.php tests/Feature/NewsletterFeedbackTest.php tests/Feature/NewsletterLocalizationTest.php tests/Feature/IndexPageTest.php
npm run build
# On unsupported virtual CPUs only: npm run build:portable
python -B tests/Browser/newsletter_smoke.py --output coverage/newsletter-browser
```

The browser command requires the pinned Playwright environment and a task-owned `TMPDIR` or `RUNNER_TEMP`; never point its fixture at real data. Follow shared-host resource admission rules rather than bypassing a denied gate. `.github/workflows/laravel.yml` runs and uploads the new smoke alongside the existing browser, quality, coverage and release checks. The final PR body supplies the later exact-head run and report evidence, superseding this pre-publication checkpoint without relabeling unexecuted local gates.
