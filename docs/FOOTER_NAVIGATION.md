# Honest, localized footer navigation

## Scope

The shared footer now has one native home link with an English or Brazilian Portuguese accessible name, a decorative logo, a visible keyboard-focus outline and a minimum 44px target. The brand is readable in the light theme. The existing desktop arrangement and stacked mobile layout remain; copyright uses the current year and a complete localized sentence with one final period.

The former Bluesky and privacy-policy `href="#"` controls had no implemented destinations. They are not presented as working links. A localized, noninteractive notice says that the privacy policy is unavailable. This change does **not** invent a social account, publish a legal policy, claim compliance or change consent/data-retention behavior. A real policy needs separately approved content and an implemented destination before restoring a link.

The home destination also needs the one-line Portuguese `messages.features.subheadline` repair: the previous one-element array caused a Blade escaping error and HTTP 500 when following the home link. It is now the same text as a scalar. This is the same prerequisite correction present in the newsletter/contact slices (PRs #21/#22), not a new homepage redesign.

## Regression evidence and reproduction

Use the lockfile-qualified PHP/Node runtimes described in [LARAVEL_13_MODERNIZATION.md](LARAVEL_13_MODERNIZATION.md), task-owned test dependencies, a process-only ephemeral application key and the normal isolated test configuration. Do not use a production environment file or database.

```sh
php artisan test tests/Feature/FooterNavigationTest.php
npm run test:tooling
npm run build:portable # local older-CPU fallback; hosted CI also runs the native build
python -B tests/Browser/footer_navigation_smoke.py --output <new-task-owned-output-directory>
python -B tests/Browser/footer_navigation_report_test.py <output-directory>/report.json
```

The browser commands require the workflow-pinned Playwright runtime, Chromium and an explicit task-owned `TMPDIR` (or CI's `RUNNER_TEMP`). The output directory must be new. `--case` is a diagnostic subset, never a complete qualification receipt. Run the full matrix and report validator after the final source/build changes.

- PHP regressions cover both actual home responses, removal of fake destinations, the unique localized native home link rather than an external configured URL, decorative logo semantics, current-year copyright, complete own-locale catalogs and escaped branding.
- Recorded RED cases include the Portuguese HTTP 500, placeholder links, duplicate home links and incorrect copyright/catalog copy. A real browser also reproduced the old 32px target and low-contrast light-theme brand before the style correction.
- The public browser matrix is EN/PT-BR × light/dark × 320/390/1280px × JavaScript enabled/disabled. Each case reaches the real footer link through Tab order, activates it with Enter, checks the successful home response and hero, then separately follows it by pointer. No synthetic link activation is used.
- Login, focused-link and home checkpoints verify the actual footer DOM, nonempty text/line geometry inside the viewport, no overlapping text, minimum target dimensions, text contrast of at least 4.5:1 and a visible keyboard outline. Screenshots mask password fields. The geometry scope is the footer, not a certification of every existing homepage section.
- The harness allows only same-origin GET/HEAD traffic and refuses mutating requests. It creates no accounts and tests no credential, newsletter, contact, SMTP or provider workflow. PHP seed and HTTP server both pin `variables_order=EGPCS`; a real owned HTTP probe verifies effective storage roots. SQLite, sessions, caches, compiled views and storage are disposable; the child-process environment is allowlisted. Server termination, closed listener and fixture removal are asserted.
- Receipts bind all declared source inputs, the build manifest and referenced public assets. Manifest-provided asset paths must be relative regular files contained canonically beneath `public/build`, rejecting traversal and outward symlinks before reading. The checkout commit/tree/dirty flag is verified separately: a dirty pre-commit HEAD is only the checkout label, not the candidate identity. Publication qualification must bind that original receipt to the frozen native candidate tree without rewriting it.
- The validator requires the exact complete case and checkpoint sets, successful navigation results, geometry, screenshot hashes and complete PNG structure/CRC/dimensions/decompressed scanlines. Its negative tests mutate in-memory receipt copies and disposable synthetic files; they must reject missing or duplicated cases, incomplete states, changed identity/source hashes, empty/invalid measurements, truncated/corrupt PNGs and unsafe asset paths without modifying the genuine receipt.

The workflow executes the complete matrix and the validator with failure propagation, uploading `footer-browser`. Source-contract tests pin both commands. This wiring alone does not prove branch-protection configuration or a hosted run: record exact-head CI and artifact verification in the PR only after they actually finish. No deployment or merge is part of this slice.
