# Profile settings usability

This bounded follow-up covers the mounted `/settings` and `/profile` journey documented in [LARAVEL_13_MODERNIZATION.md](LARAVEL_13_MODERNIZATION.md). It preserves the existing two-card layout, authenticated routes, password policy, storage policy and session-revocation behavior. It is independent of the account-menu and mobile-navigation PRs; it does not claim those unmerged changes as part of this candidate.

## Changes

- Complete English and Brazilian Portuguese copy for profile/password fields, validation attributes, upload/password guidance and mutation feedback. Catalog tests require explicit entries in both locales, matching placeholders and every referenced key.
- Native name/phone/password constraints, appropriate autocomplete, optional-phone labeling, unique labels and error descriptions, `aria-invalid`, and alert semantics for rendered errors.
- An explicit no-JavaScript notice and disabled fieldsets until Alpine initializes. These Livewire-only forms no longer appear editable without their submission mechanism.
- Opaque, contrasting action buttons, visible focus styling and minimum-height upload/action controls. The profile cards remain contained at narrow viewports without replacing the approved page layout.
- Rejected uploads retain the existing photo and render validation instead of calling `temporaryUrl()` on an unpreviewable file. A temporary preview requires both no avatar validation error and `isPreviewable()`. Previewable-but-invalid SVG/oversized images are also excluded from the preview. Valid photos are still persisted only after Save profile.

No schema, dependency, route, auth middleware or credential/session algorithm changed. Password changes continue to require the current password, rotate the remember token, advance only the current session's password fingerprint and reject stale sessions. Upload validation remains an image with a 2 MB limit; accepting files in the chooser is not server-side validation. Public-avatar retention/deletion and generic toast placement are not redesigned here.

## Regression and browser evidence

The new feature tests cover localization, both mounted routes, rendered label/error relationships, native constraints, disabled no-JavaScript forms, invalid-upload rendering, no public write before Save, and exact saved image bytes. Negative cases precede the corresponding presentation/upload corrections; the real-browser iteration additionally exposed multipart-request inspection and test-environment upload-disk assumptions in the harness. Those harness failures are not passing product runs.

The final real Chromium matrix uses isolated SQLite, database sessions, task-owned storage/caches, synthetic accounts and blocked external browser requests:

- English and Brazilian Portuguese, light/dark, at 320, 390 and 1280 pixels with JavaScript: 12 cases.
- Both locales at 320 pixels without JavaScript: 2 cases.
- 14 completed cases and 122 screenshots. Each JavaScript case has ten named checkpoints; each no-JavaScript case has one.
- Native invalid inputs cannot submit; deliberately bypassed browser validation reaches localized server errors. Profile edits persist. Invalid text uploads keep the current avatar; valid PNG uploads preview without changing the user and save exact served bytes after confirmation.
- Wrong-password and mismatched-confirmation requests leave the user unchanged. A successful password change clears the fields, changes the password hash/remember token, permits the current session's next mutation, rejects a stale mounted session before mutation and permits a fresh login with the new password.
- No-JavaScript checks prove the six inputs and two submit buttons are disabled, no mutation request is sent and the user is unchanged.
- DOM/geometry checks require complete checkpoints, nonempty measurements, controls at least 44 pixels high, measured text contrast at least 4.5, profile containment and footer separation. Source/build hashes are recorded before and after the matrix.

`tests/Browser/profile_settings_report_test.py` validates the genuine receipt and rejects 18 deliberately malformed **in-memory copies** (including dropped cases/checkpoints/screenshots, failed or incomplete cases, source drift, empty measurements, low contrast, small controls and invalid overflow attribution). These are validator tests, not 18 additional browser executions; the genuine JSON is unchanged.

Run with the qualified dependencies/runtime and a production asset build:

```bash
php artisan test tests/Feature/Profile/SettingsLocalizationTest.php tests/Feature/Profile/SettingsPresentationTest.php tests/Feature/Profile/SettingsAvatarTest.php
python -B tests/Browser/profile_settings_runtime_test.py
python -B tests/Browser/profile_settings_smoke.py --output coverage/profile-settings-browser
python -B tests/Browser/profile_settings_report_test.py --output coverage/profile-settings-browser
```

The harness uses `APP_ENV=browser`, not Laravel's unit-testing environment, so real HTTP file uploads use the actual isolated temporary disk. It explicitly configures disposable services and refuses an unsafe seed target. Multipart upload bodies must not be decoded as Livewire JSON action requests. Passwords and session identifiers are not exported in reports; screenshots mask password controls.

### Hosted failure and HTTP-isolation regression

The first hosted run, `43377909865` on `9ef0dd20a39e228df947b618a5990f14692ca189`, passed PHP tests, quality, native assets and release smoke but failed all twelve JavaScript profile journeys after avatar preview; the two no-JavaScript cases passed. That failed matrix is retained as partial evidence, not a browser PASS.

The same failure was reproduced locally by setting PHP's ambient `variables_order=GPCS`: the CLI seed resolved its run-owned storage, but the built-in HTTP server exposed the storage override through neither `$_ENV` nor `$_SERVER`. Laravel's `storagePath()` therefore used the source checkout's storage instead of the disposable target. The avatar was saved outside the served run-owned directory, and the saved-image assertion correctly failed. This was a harness-isolation defect, not a reason to weaken avatar persistence checks or alter production storage.

The seed and server now explicitly use `variables_order=EGPCS`. `profile_settings_runtime_test.py` injects the failing ambient default into actual PHP subprocesses, verifies the injection, and makes a real HTTP request to a run-owned bootstrap probe. It requires both public and private disk roots plus the application storage path to stay isolated, and verifies temporary-directory cleanup. Its RED result reproduced the missing environment and wrong roots; GREEN verifies the correction. The browser's existing exact saved-image bytes assertion remains unchanged. A replacement hosted exact-head run is still required; the successful local regression does not retroactively qualify the failed run.

## Local qualification before publication

| Gate | Actual result |
|---|---|
| New focused PHP tests | 33 passed, 677 assertions |
| Full PHP suite with PCOV | 589 passed, 3,412 assertions; no failures, errors or skips |
| Clover statement coverage | 1,584 / 1,730 statements, 91.56%; profile Settings class 100% |
| Repository-wide Pint | Passed |
| Larastan | No errors |
| Composer strict validation/platform checks/audit | Passed; no reported advisories |
| npm audit | Zero reported vulnerabilities |
| Portable production build | Passed |
| Node tooling | 12 passed, including real portable build/manifest and installed Octane watcher checks |
| Chromium matrix / report validator | 14 cases, 122 screenshots; 18 malformed report copies rejected |
| Isolated production-mode release smoke | Config/routes/views cached; actual disposable SQLite connection and auth/session route middleware checked; 19 migrations applied, rolled back to zero and reapplied; foreign-key checks empty; owned temporary directory removed |
| Diff whitespace check | Passed |

These are local receipts for the unchanged executable/test/build inputs, followed by this documentation file. They are not a new execution after adding the report. Native local Rolldown is not claimed on the older virtual CPU; hosted native build, exact-head CI and independent exact-tree review remain pending at this pre-publication checkpoint. The PR's verified evidence will supersede that publication status without rewriting the frozen source merely to update a report.

## Visual scope and remaining limits

Representative final screenshots were inspected in English/Portuguese, desktop/mobile, light/dark and no-JavaScript states. The changed profile controls and errors were legible and contained. A black avatar circle is the valid tiny PNG fixture, and magenta password regions are deliberate screenshot masks. Native file-chooser text follows the browser/OS locale rather than the application's catalog.

There are explicit inherited shell exceptions, not blanket accessibility waivers:

- With JavaScript disabled, the unchanged legacy account menu can overflow to the right and occlude the page heading. The harness accepts only overflow attributed entirely to that menu, with its measured extent accounted for; any profile overflow or unrelated shell overflow fails. This does **not** certify the menu or claim the entire no-JavaScript page is overflow-free.
- An existing transient top-right toast can overlap avatar guidance on narrow screens before its three-second dismissal. This slice does not change the toast component or claim every transient shell overlay is overlap-free.
- Existing pale shell branding and duplicated Portuguese footer punctuation remain outside this profile slice.

Chromium geometry/contrast checks and selected screenshots are not exhaustive accessibility certification, assistive-technology testing or cross-browser evidence. No real SMTP delivery, production concurrency, merge or deployment is established by this work.
