# Transient notification feedback

## Scope

This slice repairs the mounted toast presentation without changing notification producers, the persistent inbox, authorization, or delivery services:

- A toast's own dismiss button updates its Alpine visibility state. Pointer, Enter and Space activation are covered; dismissal does not depend on an unrelated hard-coded element ID.
- Toast IDs and Livewire keys combine the component identity and entry index, preserving surviving cards when another message is appended.
- English and Brazilian Portuguese catalogs supply the dismiss label and success/error/warning/information descriptions. Unknown kinds retain the information fallback. Messages remain Blade-escaped text.
- Narrow-screen messages wrap without pushing the close control outside the viewport. The close target is at least 44 × 44 CSS pixels, and the toast layer is above the existing navbar.
- The existing three-second automatic expiry and transient overlay positioning are preserved. A toast can temporarily cover part of the navbar; it does not reflow the page. This is not a redesign of the shell or a full accessibility certification.

The current master profile producer falls back to its English success message in the Portuguese fixture. Profile message localization is owned by the separate profile-settings slice, not changed here. The transient listener's payload and clear action remain unchanged.

## Regression evidence

The implementation was developed through focused failures before corrections: untranslated dismiss/kind labels, duplicate identities, ineffective keyboard dismissal, a 32-pixel close target, long-text overflow, navbar occlusion of the toast, and insufficient light-mode close-icon contrast. The final view addresses those failures without replacing the listener or adding a new JavaScript dependency.

The focused PHP run passed 22 tests with 104 assertions, including the existing notification UI tests. It checks the real Livewire dispatch boundary, stable identities, independent literal translations, escaped markup and clearing.

The complete local PHP suite subsequently passed 572 tests with 2,818 assertions, no failures or skips, and 91.11% line coverage. Pint, Larastan, Composer validation/platform checks and dependency audits passed. All 12 tooling tests passed, including the portable asset build and real Octane watcher check. Hosted native-build and exact-head qualification remain separate publication gates.

The final local Chromium matrix passed all 12 cases: English/Portuguese × light/dark × 320/390/1280 pixels. Each case captures five checkpoints (60 PNGs total) and exercises:

1. A real authenticated profile save, its matching HTTP action, persisted database value, server-dispatched notification and mounted listener, followed by Enter dismissal.
2. Synthetic warning/information events through that same mounted listener, Space dismissal, independent remaining-card visibility, appending another event without resurrecting the dismissed card, and pointer dismissal.
3. A long literal message containing markup, safe text rendering and wrapping, followed by pointer dismissal.
4. An unknown kind's information fallback and the unmodified automatic expiry.

Synthetic events prove presentation/listener behavior, not the business transitions that might produce those messages. Every manual dismissal must finish before the earliest possible automatic expiry, so waiting for the timer cannot satisfy the dismissal assertions.

Across the local matrix, minimum measured message contrast was 4.836, minimum close-control contrast was 3.033, and minimum close-target width/height were 44 pixels. Automatic hiding was observed between 3.437 and 3.498 seconds including request/render time. There were no page errors. Geometry and hit-testing verify each toast, message and control stays visible and unobscured at the capture checkpoints; these measurements do not certify unrelated page text or every possible message length.

Representative Portuguese mobile light/dark and English desktop screenshots were visually inspected. Long text and both stacked close controls remain visible; the existing status icon is decorative, not a second dismiss button. Password fields are deliberately masked in screenshots.

## Fixture and receipt boundaries

`tests/Browser/toast_feedback_smoke.py` uses a fresh run-owned SQLite database, storage, compiled views, bootstrap caches, session namespace and loopback server per case. The seed refuses non-browser, cached, unit-test or non-owned environments before migration. Child-process environment variables are allowlisted; mail and queue are explicitly isolated. PHP seed and HTTP commands pin environment-variable exposure, and a real HTTP bootstrap probe verifies the storage roots. External browser requests are blocked. Each owned server is terminated/reaped, its listener checked closed, and the temporary fixture removed.

The report includes the checkout commit/tree/dirty label and required source/build hashes. A dirty checkout label is not the identity of the candidate: the reviewed native tree and its file hashes bind pre-commit evidence separately. Hosted evidence must be matched to the actual GitHub checkout and published head before readiness is claimed.

The validator requires the exact case/checkpoint/screenshot sets, successful terminal states, nonempty geometry, finite contrast, all behavioral assertions and complete source identity. It checks PNG structure, checksums and bounded decompression rather than accepting a file signature alone. Its regression runner rejects 39 in-memory or temporary-file corruptions without altering genuine evidence, including dropped/duplicate cases, failed assertions, source/checkout drift, missing geometry, clipping, invalid contrast and malformed PNGs.

## Reproduction and CI

With the lock-matching PHP/Node runtimes, dependencies and Playwright Chromium installed, run:

```sh
php artisan test tests/Feature/Notifications/ToastPresentationTest.php tests/Feature/Notifications/NotificationUiTest.php
node --test --test-reporter=spec tests/Tooling/ci-workflow.test.mjs
npm run build:portable
python -B tests/Browser/toast_feedback_smoke.py --output /absolute/task-scratch/toast-browser
python -B tests/Browser/toast_feedback_report_test.py /absolute/task-scratch/toast-browser/results.json
```

Set `TMPDIR` (or CI's `RUNNER_TEMP`) to owned scratch. Output must be a new directory. The optional single-case flag is for local diagnosis only; the normal report validator requires the full matrix. Rebuild after changing view utility classes, then regenerate evidence if any hashed input or asset changed.

The required PHP-test CI job runs the full toast matrix and validator, then always uploads `toast-feedback-browser`. Existing PHP quality, native/portable asset, full coverage, release cache/migration/rollback, event-history and dashboard checks remain intact. Local portable-build and browser evidence do not replace hosted native-build or exact-head CI qualification; publication receipts in the PR supersede this pre-publication checkpoint.

No production deployment, SMTP delivery, screen-reader session, non-Chromium browser certification, or persistent-inbox behavior change is claimed.
