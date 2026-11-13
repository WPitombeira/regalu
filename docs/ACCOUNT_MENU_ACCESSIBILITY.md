# Account menu accessibility and localization

This slice improves the authenticated account disclosure in the shared header. It preserves the existing layout, routes, authentication/session middleware and CSRF-protected POST logout. There are no schema, dependency or authorization changes.

## Behavior

- The trigger has an English or Brazilian Portuguese accessible name, `aria-controls`, and an `aria-expanded` value that follows the actual open state.
- Enter and Space activate the native button. Tab follows the native Dashboard → Settings → Sign out order without a focus trap. Escape closes the open disclosure and returns focus to its trigger; moving focus outside or clicking outside closes it too.
- Links and the logout button remain ordinary interactive elements rather than claiming an ARIA application-menu pattern that the component does not implement.
- The panel is anchored inside a positioned container and right-aligned. Long account names and email addresses are truncated instead of expanding the page beyond a narrow viewport.
- Accounts without a photo use a local decorative SVG. A supplied photo replaces it only after successful loading; failed photos leave the fallback visible. The trigger's accessible name does not depend on image availability.
- Settings, Sign out, and the main-navigation button's accessible label are translated. The existing shared Dashboard label is reused. Names and email addresses are user data, not translated copy.

## Regression coverage

`tests/Feature/Auth/AccountMenuTest.php` covers absent/empty avatars, locale-specific literal labels, real destination routes, disclosure semantics, POST/CSRF markup, supplied-image fallback, escaped identity/image attributes, guest isolation and translation availability without fallback. Existing `LogoutAndRegistrationUiTest` coverage continues to verify that GET logout is unavailable and POST logout invalidates the session.

`tests/Browser/account_menu_smoke.py` uses the existing disposable Laravel harness, synthetic users, isolated SQLite/database sessions and blocked third-party requests. It exercises real login, keyboard opening and tab order, Escape/focus return, tab-out/outside-click dismissal, settings navigation, POST logout and subsequent guest redirection. Each locale runs at 1280×900, 390×844 and 360×800. The smoke stores five screenshots and geometry checkpoints per case and rejects horizontal page/control overflow or JavaScript exceptions.

Avatar URL/name updates in the disposable database are explicitly **presentation fixtures**, not evidence of avatar upload or profile editing. The valid image is a local repository asset; the missing-image request intentionally returns 404 to exercise the fallback.

## Qualification checkpoint before publication

- Focused PHP regressions after formatting: **13 passed, 72 assertions**, no failures/errors/skips.
- Local Chromium matrix: **6 passed**, with all five geometry checkpoints checked in each case. Recorded browser execution used the candidate UI and portable assets; workflow/documentation and an explicit test facade import were added afterward without changing that UI.
- Scoped Pint, strict Composer validation, platform requirements and Composer/npm audits passed; the audits reported no vulnerabilities.
- The portable production asset build passed before the final local browser run. Native Rolldown has a documented SIGILL limitation on this virtual CPU; a native build is not claimed locally for this slice.
- Full local Larastan was **not run**: the resource guard refused admission because host load exceeded its threshold. The full PHP/coverage suite, native build, tooling and release cache/migration smoke remain required hosted gates, not inferred local passes.
- Independent exact-tree source review and exact-head hosted CI are pending at this checked-in checkpoint. The PR's verified run and artifact links supersede this historical publication status; source review alone is not runtime or release approval.

The existing CI workflow now executes the account-menu browser matrix and uploads results, screenshots and geometry even when that step fails. The PHP suite, quality/audit job, native/portable assets, existing browser suites and disposable release smoke remain required.

## Limits and follow-up

This is targeted Chromium keyboard/geometry evidence, not an exhaustive accessibility audit, screen-reader certification or Safari/Firefox qualification. Existing theme/contrast design is unchanged.

The separate primary mobile-navigation hamburger still needs its own interaction wiring, and the guest header's mobile login/navigation layout remains a follow-up. Translating that button's accessible label does not claim to repair the primary-navigation workflow. Keep that change separate from this account-disclosure slice.

No merge or deployment is part of this checkpoint.
