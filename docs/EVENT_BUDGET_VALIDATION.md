# Event budget validation

## Contract

The mounted `amigo-secreto.create-event` form accepts either budget limit independently, including zero. Blank limits persist as `NULL`; `0` persists as `0.00`. Supplied values must be nonnegative ordinary decimal numbers with at most two fractional digits, bounded by the existing `decimal(10, 2)` columns (`99999999.99`). When both limits are supplied, the maximum must be at least the minimum. Invalid submissions create neither an event nor an organizer participant and do not dispatch success.

Validation uses Laravel's numeric/decimal/bound rules and conditional `gte:budget_min`, without converting values to floating point in application code. Empty `#[Rule]` attributes retain Livewire property-validation behavior while the dynamic `rules()` method supplies the cross-field condition. This changes neither the schema, existing records, currency representation, authorization, family archival rules, recipient privacy, nor date validation. Scientific notation and fractional cents are rejected instead of silently rounded. A comma decimal separator is not an alternative server wire format; browser-localized number controls submit their normalized numeric value.

Labels, guidance and server errors have independent English and Brazilian Portuguese catalogs. Each invalid budget input has a unique alert and associated `aria-describedby`/`aria-invalid`. Native number constraints remain active. Budget inputs stack below the `sm` breakpoint and have a 44px minimum height; desktop keeps two columns. The real 320px regression showed the maximum value clipped in 103px-wide inputs with 42px height before this scoped correction.

## Verification

PHP regressions in `tests/Feature/AmigoSecreto/EventBudget*Test.php` cover zero, absent/blank/whitespace limits, equal and single-sided bounds, upper boundary, reversed intervals, precision, exponent notation, negative/nonnumeric values, no side effects on rejection, correction/retry, persisted values and actual detail rendering. Presentation tests mount the real route and verify unique labels, native constraints and localized ARIA error associations. Existing event/privacy/family-authorization tests remain applicable.

Run the browser commands with the qualified PHP/Node runtimes, built assets and Playwright 1.65.0:

```sh
npm run build:portable
python -B tests/Browser/event_budget_smoke.py --output <new-task-owned-output-directory>
python -B tests/Browser/event_budget_report_test.py --output <same-output-directory>
```

The complete matrix contains EN/PT-BR × light/dark × 320/390/1280px JavaScript journeys, plus one 320px light no-JavaScript case per locale. Each JS journey logs in through the actual UI, proves native rejection, exercises server validation (with a synchronous, immediately restored native-validation bypass only for precision/upper-bound negative probes), then saves zero, each single-sided zero, no limits, and the upper one-cent interval. It reads the disposable database and waits for each exact created-event destination. Budget keyboard order, input/text contrast, visible geometry, full number width, ARIA, unchanged rejected drafts and page errors are asserted. No-JS evidence is explicitly limited to server-rendered budget semantics, keyboard order and native invalid-value refusal: this is not certification of the whole form's no-JS submission behavior.

The fixture uses a fresh SQLite database with actual migrations, allowlisted subprocess environment, run-owned session/storage/cache paths, array mail, and loopback-only browser requests. Ephemeral credentials remain in memory. PHP seed and HTTP server both set `variables_order=EGPCS`; an HTTP bootstrap probe verifies storage roots. Cleanup checks confirm the listener and fixture directory are removed. SQLite receipts do not claim another database dialect or concurrency behavior.

Receipts require the exact matrix, all checkpoint measurements and screenshots, terminal success, persistence/refusal evidence, full source/build hashes and unchanged checkout metadata. A dirty checkout's HEAD is labeled as checkout metadata, not the tested candidate: the publication review independently binds its actual bytes to a native candidate tree. Manifest assets are confined canonically to the build directory; PNG chunks, CRCs, dimensions and bounded decompression are checked. The validator's mutation tests reject partial, empty, duplicated, modified and corrupt evidence without replacing genuine captures.

`.github/workflows/laravel.yml` runs the complete browser matrix and validator with fail-fast shell behavior and retains `event-budget-browser`. Local portable builds and receipts do not establish hosted native-build success, exact-head CI, branch-protection requirements, merge or deployment. Those are verified separately at publication. Visual qualification is scoped to budget controls and feedback, not unrelated event form labels, dates or the shared shell.
