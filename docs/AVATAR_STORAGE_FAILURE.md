# Avatar storage failure and retry

## Contract

`Profile\Settings::updateProfile()` must not acknowledge a profile save when its new avatar could not be written. The locked Livewire `TemporaryUploadedFile::storeAs()` returns its constructed path without checking the destination disk's `put()` result. With the public disk's default `throw=false`, trusting that path can replace the saved avatar with a missing-file URL.

The action now writes the validated temporary upload's stream through the public disk and checks the actual result. A false result or a Flysystem filesystem exception produces an inline, localized `avatar` error and returns before the user update, preview replacement, upload reset, or success notification. The stream is closed in `finally`. Name, phone, previous avatar, and the selected upload remain available for retry. No global disk configuration or upload validation is weakened.

The same action still validates the fields and resolves the authenticated user before writing. Profile edits without a new photo do not depend on avatar storage. Password and session behavior are unchanged.

## Scope limits

- This is destination-write failure handling, not a transaction across the database and object storage. A later database failure can still leave a newly written file; old-avatar garbage collection is outside this change.
- Local adapter behavior is exercised with real filesystem obstructions, including both `throw` settings. No S3 outage, distributed concurrency, or production deployment is claimed.
- The new error has English and Brazilian Portuguese catalogs. The rest of the profile page on this base still has inherited English copy; PR #25 addresses its separate usability/localization work. This slice does not redesign the page or certify global contrast/touch-target compliance.

## Regression evidence

`tests/Feature/Profile/SettingsStorageFailureTest.php` uses a valid PNG and the real local filesystem adapter provided by Laravel's isolated test disks, not a mocked upload/store result:

- A directory at the exact destination forces `put()` to return false or throw `UnableToWriteFile`, even for a privileged runner. The precondition is asserted before the real component action.
- English/Portuguese cases preserve the entire persisted user row, previous file bytes, draft fields and selected temporary upload, with an inline error and no success dispatch.
- Removing only that obstruction permits retry of the same upload, then verifies the new profile fields, saved PNG bytes, success dispatch and unchanged credentials.
- A file blocking the `avatars` parent directory exercises `UnableToCreateDirectory`.
- No-avatar edits, action denial after logout, and validation-before-storage are separate invariants.

The silent-failure and throwing-write cases were observed failing before their fixes. The focused final suite passed **9 tests / 158 assertions**. The complete suite passed **565 tests / 2,893 assertions**, with no failures/errors/skips and **1,583 / 1,731 covered statements (91.45%)** in Clover; the native runner rounds this to 91.5%. An earlier local coverage run used a sibling worktree's PHP INI and reported zero coverage despite passing tests. Its reports were retained separately; the passing rerun verified the candidate-specific PCOV directory in the underlying PHP child first.

Local Pint, Larastan, Composer validation/platform checks/audit, npm audit, all **12 tooling tests** (including portable build and real Octane watcher), and the portable production build passed. The local portable build emits Node's experimental WASI warning. Hosted native-build/CI qualification is pending at this document's freeze; the PR must record its exact-head outcome before readiness.

## Real browser fault injection

Run with the repository's qualified PHP and Playwright 1.65.0 runtime and an explicit scratch directory (`RUNNER_TEMP` or `TMPDIR`):

```sh
python -B tests/Browser/avatar_storage_report_test.py
python -B tests/Browser/avatar_storage_smoke.py --output /absolute/task-owned/new-evidence-directory
python -B tests/Browser/avatar_storage_report_test.py --report /absolute/task-owned/new-evidence-directory/results.json
```

The output directory must not already exist. `--case <locale>-<scheme>-<width>` permits a focused journey but is not a complete-matrix receipt.

The full local matrix passed **8 cases**: English/Portuguese × light/dark × 390/1280px. Each case captures initial, selected, refused and saved states (**32 PNGs**). The journey performs real HTTP login/upload/update calls, matches the `updateProfile` JSON response rather than multipart upload requests, and verifies:

1. The prior avatar is served with its original bytes.
2. A run-owned filesystem obstruction denies storage; the localized error is rendered, draft fields and signed upload preview remain, the complete user row is unchanged, and no success event is dispatched.
3. After removing only that fixture obstruction, retry succeeds without reselecting the file. The database fields, saved file bytes, served bytes, and subsequent reload agree.
4. Each checkpoint has nonempty form geometry within the viewport and no page errors. Screenshots have valid PNG chunks/CRCs and decompressed scanlines, not only a PNG signature.

The PHP seed refuses non-isolated database/service/disk settings before migration. Browser children inherit an allowlist of runtime-location variables, not the parent's application credentials. Their environment is `APP_ENV=browser`, because a unit-test environment selects Livewire's test upload disk. Both seed and HTTP server explicitly set `variables_order=EGPCS`; a real HTTP bootstrap probe checks the effective private/public storage roots. The runtime regression injects an ambient `GPCS` default and checks temporary-directory and listener cleanup. Ephemeral keys/passwords remain in process memory, and screenshot password inputs are masked.

The receipt validator rejects missing/duplicate/unexpected cases, incomplete stages, false success, page errors, source drift, missing checkpoints, empty/out-of-bounds geometry and missing/corrupt screenshots. Its negative mutations consume the genuine report without rewriting evidence. Both runtime tests and the genuine-report mutation test passed. Representative desktop English and mobile Portuguese refusal screenshots were visually inspected: the new error is legible and not clipped or overlapping controls.

CI runs the runtime check, full browser matrix and receipt check in order and always uploads `avatar-storage-browser`. Existing PHP coverage, release migration/cache smoke, event-history/dashboard journeys and native/portable asset gates remain required. Publication, exact-head hosted CI, and merge/deployment are separate boundaries; no merge or deployment is authorized by this qualification.
