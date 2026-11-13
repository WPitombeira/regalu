# Mobile header navigation

## Scope and behavior

The primary navigation now works for guests and signed-in users below the existing 48rem / 768px breakpoint. This is a navigation disclosure containing ordinary links, not an ARIA application menu.

- A real button toggles the uniquely identified primary navigation panel and synchronizes `aria-expanded`.
- Enter and Space activate the button. Tab reaches the links in document order; Escape closes the disclosure and returns focus to its trigger. Clicking outside or moving focus outside the header closes it without trapping focus.
- Widening to desktop closes the mobile disclosure state while keeping desktop links visible. Returning to a narrow viewport starts closed.
- Guest sign-in remains visible outside the collapsed panel, including at 360px. Reduced mobile inset and desktop no-wrap layout prevent the header controls from overlapping.
- Members can follow the existing dashboard, families, wishlists and Secret Santa routes. No routes, authorization rules, session behavior, notification data or logout method were changed. Logout remains a CSRF-protected POST.
- Accessible navigation labels are present in both English and Brazilian Portuguese. The previously missing Portuguese family label now resolves without English fallback.

The real Portuguese guest journey also exposed an existing homepage HTTP 500: `messages.features.subheadline` was a one-element array rendered as escaped text. It is now the same Portuguese sentence stored as a string. The fix does not weaken escaping or change the page structure.

## Verification

`tests/Feature/Auth/HeaderNavigationTest.php` checks the rendered guest/member navigation in both locales, unique panel/trigger markup, accessible names, exact link labels, catalog ownership without fallback, guest sign-in placement, and successful public entry-page responses in both languages. Existing logout/registration tests retain the authenticated POST/logout contract.

`tests/Browser/header_navigation_smoke.py` runs ten cases: English and Portuguese at 360, 390, 768, 1024 and 1280 pixels. Each uses a fresh browser context, a disposable migrated SQLite fixture and the real Laravel/Livewire application. External requests are blocked; no real accounts, email delivery or external providers are used.

Each case exercises public entry, visible sign-in, login and all four authenticated primary destinations. Narrow cases additionally exercise Enter, Space, Escape/focus return, outside click, Tab-out and desktop/mobile resizing. The resize check keeps focus on a link that remains visible so hiding the trigger cannot accidentally satisfy the dismissal assertion through focus loss. Every captured checkpoint immediately checks horizontal overflow, control bounds and non-overlapping header siblings. Browser page errors and HTTP 5xx responses fail the journey. Evidence includes screenshots, per-case geometry and a result matrix with source and asset-manifest identities.

Run with the repository-qualified PHP/Node runtimes and a Python environment containing Playwright 1.65.0 plus Chromium:

```bash
php artisan test tests/Feature/Auth/HeaderNavigationTest.php tests/Feature/Auth/LogoutAndRegistrationUiTest.php
npm run build
python -B tests/Browser/header_navigation_smoke.py --output coverage/header-navigation-browser
```

`npm run build:portable` remains the documented alternative on older virtual CPUs. A portable build is not native-build evidence. The required GitHub workflow runs this browser matrix after the existing lifecycle/dashboard journeys and uploads its results, geometry and screenshots.

### Pre-publication evidence and limits

The focused PHP run passed with 11 tests and 77 assertions. The local portable build and all ten browser cases passed. Discriminating failing runs were retained for the inert member trigger, hidden guest sign-in, missing locale copy, Portuguese homepage 500, outside-click dismissal, Tab-out dismissal and focused-link resize behavior. These local browser results were produced from the uncommitted candidate, not a published exact-head checkout. Independent source review, full hosted coverage/analysis, native build, tooling and release smoke are separate gates; their final status belongs in the PR evidence rather than being inferred from these local checks.

## Boundaries and composition

This master-based slice intentionally does not duplicate the separate account-menu accessibility change in PR #19. Both touch `components/ui/user/card.blade.php`: when composing them, preserve that PR's accessible account disclosure/avatar fallback and this slice's shared primary trigger and always-visible guest sign-in. Re-run both browser suites on the composed tree; neither isolated suite qualifies their future integration.

The existing guest About/Features links still target the homepage and Contact retains its current anchor. Broader marketing translation, contact-form layout/wiring and account-dropdown behavior are outside this slice. The base's broken default-avatar image remains visible until the independent account-menu change is integrated. No database migration, permission expansion, merge or deployment is part of this change.
