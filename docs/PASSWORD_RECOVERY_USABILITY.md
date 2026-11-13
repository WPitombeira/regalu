# Password recovery usability

This bounded slice builds on `e561bc0ed7488cf60e330c4a38caac17a669e770` and covers the existing HTML forgotten-password and reset-password journeys. It does not introduce a different authentication policy, provider, schema, queue or reset-token implementation.

## User-visible contract

- English and Brazilian Portuguese headings, labels, help, actions, validation attributes and feedback use complete `password_recovery` catalogs.
- Every well-formed reset-link request receives the same conditional acknowledgement, including an unknown account and a broker-throttled repeat. The wording is not a claim that SMTP delivery was verified.
- Invalid, expired and consumed reset links produce actionable, localized feedback with a real link to request another one. Unknown accounts use the same invalid-link message.
- Both pages are native CSRF-protected POST forms and remain usable without JavaScript. Existing guest middleware, password rules, broker expiration/throttling, token consumption, remember-token rotation, authentication and redirect behavior remain in place.
- Labels, required/autocomplete/minlength attributes, error IDs, `aria-invalid`, `aria-describedby`, status feedback and keyboard navigation expose the form's state without relying on color alone.
- Real Tailwind blue utilities replace undefined `primary` utilities. The baseline build rendered transparent submit backgrounds in all twelve initial locale/theme/viewport form captures. Controls now have a visible opaque background and minimum 44px touch height. Flexible minimum-height layout accommodates multiline error feedback without colliding with the footer.
- Non-string email prefills are discarded at rendering, both from a malformed query and flashed invalid input. Server validation still rejects arrays; scalar prefills and feedback remain HTML-escaped. The regression reproduced HTTP 500s before this rendering correction.

## Verification recorded before publication

| Gate | Executed local result |
|---|---|
| Focused recovery feature suites | 35 tests, 359 assertions |
| Complete PHP suite with PCOV | 584 tests, 3,064 assertions; zero failures/errors/skips; 1,577 / 1,726 statements covered (91.37%) |
| Repository Pint and Larastan | Passed; no static-analysis errors |
| Composer strict validation and Composer/npm audits | Passed; no reported vulnerabilities |
| Node tooling | 12 tests passed, including real portable Vite build, Octane watcher and recovery CI wiring contract |
| Chromium recovery journeys | 14 complete cases, 126 screenshots; no recorded JavaScript errors |
| Browser geometry and opaque-text contrast | All recorded checkpoints passed; lowest measured form text contrast 5.2457:1 |
| Report-validator negative controls | Duplicate case, incomplete stage, changed source, empty measurements and missing screenshot copies all rejected |

The PHP changes followed separate RED/GREEN cycles for honest acknowledgements, rendered presentation/error relationships, localized validation and malformed prefills. Positive invariants remained in the suites. The workflow contract test is supplemental wiring coverage, not browser execution evidence.

Browser matrix: `en` and `pt_BR`, light/dark, 320/390/1280px with JavaScript; plus both locales at 320px/light without JavaScript. Height is 844px. Each case uses a fresh context and a separately migrated, disposable SQLite database with persistent database sessions and array mail. Real browser POSTs exercise known/unknown/repeated requests, native constraints, server-required/mismatch errors, malformed GET/POST input, invalid/expired/consumed-token refusals, successful reset, credential persistence, authentication, a fresh guest replay and both recovery navigation links. The expired timestamp is a labelled synthetic precondition, not a real elapsed-time test.

The browser harness verifies effective isolated service/storage configuration before migrating, blocks third-party browser requests, masks password inputs in screenshots, and does not retain reset capabilities, request bodies, HTML dumps or raw server/exception logs. Before/after hashes bind source and built assets; matrix validation requires exact case/checkpoint/image sets, nonempty measurement counts, terminal success, authentication/replay flags and matching per-case reports. Temporary databases and owned listeners are cleaned up.

Representative final screenshots were visually inspected for Portuguese narrow required/invalid feedback, English desktop reset and Portuguese no-JavaScript request acknowledgement. No clipping, form/footer overlap or unreadable form copy was observed. Existing shared-shell light branding contrast and Portuguese footer punctuation remain outside this slice.

## Reproduction

Use the pinned runtimes and isolated test environment described in [LARAVEL_13_MODERNIZATION.md](LARAVEL_13_MODERNIZATION.md); never use production databases or credentials.

```sh
php artisan test tests/Feature/Auth/PasswordResetTest.php tests/Feature/Auth/PasswordRecoveryPresentationTest.php tests/Feature/Auth/PasswordRecoveryLocalizationTest.php
composer test:coverage
composer lint
composer analyse
npm run test:tooling
python -B tests/Browser/password_recovery_smoke.py --output coverage/password-recovery-browser
```

The browser Python must have Playwright 1.65.0 and compatible Chromium installed. Set `TMPDIR` or `RUNNER_TEMP` to task-owned scratch. `--case` is available only for focused diagnosis; qualification and CI run the complete matrix.

## Remaining boundaries

- Hosted exact-head CI, native Rolldown and release cache/migration/rollback smoke are **pending at this source freeze**. The PR must supply their actual run/artifact evidence before being marked ready. Local portable build is not native-build evidence.
- This proves sequential broker replay refusal and its persistent token-table throttle, not parallel token consumption, account-enumeration timing resistance, new IP admission controls or production-dialect concurrency.
- Array mail and notification fakes do not certify real SMTP delivery or localized email contents. The successful-reset session message is tested at the redirect producer; a dashboard success banner is not claimed.
- Chromium/opaque text/geometry checks and representative visual inspection are not exhaustive accessibility, assistive-technology, Firefox/Safari, macOS/Windows or production-runtime certification.
- No merge or deployment is included.
