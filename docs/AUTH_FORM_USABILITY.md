# Login and registration usability

## Scope

This slice repairs the existing `home.login-form` mounted at `/login`. It keeps the established login-attempt admission, credential validation, session handling, intended redirects, registration persistence and remember-me policy unchanged. No new identity provider, route, migration or dependency is introduced.

- English and Brazilian Portuguese catalogs cover headings, labels, switches, password guidance, validation attributes, domain rejections and success notifications.
- Every input has a unique ID, associated visible label and appropriate autocomplete/native constraints. Server errors remain escaped and appear beside the affected field with `aria-invalid`, an associated description and an alert. Generic login rejection does not distinguish throttling from invalid credentials.
- Cards use bounded fluid width and natural height rather than fixed mobile heights. The remember/reset row wraps, and active links and primary buttons have explicit colors; links are also underlined.
- Keyboard activation switches between login and registration and focuses the first field in the newly visible form.
- Apple and Google controls are natively disabled with associated localized unavailable-service text. This is not OAuth implementation or provider qualification.
- Without JavaScript, a localized explanation remains visible, registration starts hidden, and the form fieldsets are disabled. This avoids a misleading fallback submission; it does not add a non-JavaScript authentication endpoint.

## Regressions and verification

The implementation was developed through bounded RED/GREEN checks for missing registration errors, label/native semantics, mobile field width, localized validation and success messages, unavailable-provider controls, primary-button contrast, keyboard focus, no-JavaScript behavior and self-referencing accessible descriptions. A real narrow-screen reset-link probe failed at approximately 1.74:1 contrast before the link styling correction and passed at approximately 6.83:1 afterward. The workflow contract failed before the auth browser gate was added.

Local qualification before publication:

- Focused presentation/localization plus existing login/registration tests: **37 tests, 513 assertions**.
- Full PHP suite with PCOV: **587 tests, 3,220 assertions**, no failures/errors/skips; Clover statement coverage **1,577 / 1,730 (91.16%)**. The console rounds this to 91.2%.
- Full Pint and Larastan: passed. Portable Vite production build: passed on Node 26.13.0; the official WASM fallback emits its expected experimental WASI warning.
- CI configuration contract: **8 tests passed**, including required browser execution and bounded artifact upload.
- Complete tooling suite: **12 tests passed**, including the real portable build/manifest and Octane file watcher.
- Real Chromium matrix: **14 cases and 74 screenshots**, with unchanged source/asset provenance, complete case-key set and no page errors. The measured contrast of enabled auth buttons/links was at least **6.83:1** in these captures; this scoped opaque-color check is not a complete accessibility audit.

The browser script `tests/Browser/auth_forms_smoke.py` covers EN/PT × light/dark × 320/390/1280px, plus JavaScript-disabled EN/PT at 320px. Each JavaScript journey exercises native invalid-submission refusal, separately scoped server validation, password mismatch, duplicate email, successful registration persisted in SQLite, fresh-session invalid login, successful login with the intended settings redirect, and remember-token presence. Screenshots capture login, registration and each rejection. Two complete keyboard switch cycles verify focus transfer. Every checkpoint immediately checks horizontal bounds, minimum field width, card/footer separation and active-control contrast.

The runner reuses the existing disposable browser fixture: isolated SQLite/cache/view paths, process-only random credentials and application key, no outgoing provider requests, masked password screenshots, and owned server cleanup. Only structured case metadata, fixed reason codes, hashes, geometry and screenshots are retained. Raw requests, credentials, exception snapshots and bootstrap traces are not uploaded. Required source files and built assets must exist before provenance is accepted.

## Reproduction and remaining gates

Use the pinned PHP/Node dependencies and an isolated application environment; never point browser or migration fixtures at application data. Build after the final utility-class edit, then run:

```sh
npm run build:portable
php artisan test --compact tests/Feature/Auth/AuthFormPresentationTest.php tests/Feature/Auth/AuthFormLocalizationTest.php tests/Feature/Auth/RegistrationTest.php tests/Feature/Auth/LoginPageTest.php
node --test tests/Tooling/ci-workflow.test.mjs
python -B tests/Browser/auth_forms_smoke.py --output coverage/auth-forms-browser
```

Apply the host's resource admission policy to heavy gates. The browser fixture requires `RUNNER_TEMP` or `TMPDIR` pointing to owned disposable storage and the pinned Playwright runtime. Hosted CI runs the auth matrix after native assets and the existing release smoke, and publishes `auth-forms-browser` JSON/screenshots even on failure.

Independent exact-tree review and hosted checks are publication gates; they were not yet complete when this document was frozen. The PR records their subsequent results on the exact published head. A local portable build does not certify native bundling. Browser array-cache fixtures do not prove cross-request throttling; unchanged shared-store admission/concurrency tests remain in the PHP suite. Remember-token presence does not independently prove browser-restart cookie recovery. No screen-reader session, other browser engine, OAuth delivery, deployment or legal/accessibility certification is claimed. Existing shell/footer copy and styling are outside this bounded form slice.
