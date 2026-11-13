# Optional, private-first onboarding

This implements a small authenticated onboarding journey from the historical [product definition](PRODUCT_DEFINITION.md), building on the [actionable dashboard](ACTIONABLE_DASHBOARD.md). It is not a forced setup wizard or a complete localization of existing wishlist forms.

## User journey

- The dashboard exposes **Getting started / Primeiros passos** for both new and returning users.
- `/getting-started` explains three steps: create a wishlist, add a first gift, then review privacy and sharing.
- Creation and gift entry reuse the existing authorized forms. New wishlists retain their `PRIVATE` default; a gift needs only a name. Links, images and prices remain optional.
- The guide links to the current list and its settings. Keeping it private is a valid outcome; there is no compulsory share, invitation, or family membership.
- A visitor may return to the dashboard at any step and reopen the guide later. Registration/login redirects are unchanged.

## Resumption and data boundaries

The guide recalculates progress on each render from the authenticated user's **active owned** lists. It prefers the newest list containing an item, otherwise the newest empty list. Shared lists, even with EDIT access, and archived lists do not count. Deleting/archiving/transferring a list or removing its last item affects the next render.

Only `id`, `name`, `privacy` and an item-existence boolean are selected, with a single returned list. Item names, prices, purchase status, buyer identity and event assignments are not loaded into the guide. No model or list identifier is retained as a public Livewire property. Names use escaped Blade output.

The route uses the existing `auth` and `auth.session` middleware, with Laravel's core session middleware already persistent for Livewire requests. The component checks authentication before every render and scopes its fresh database query to that principal. There is no new mutation endpoint or schema change.

## Privacy explanation

- **PRIVATE:** ordinary wishlist access remains owner-only.
- **FAMILY:** people sharing an active family with the owner may see the list; this reflects the existing policy, not a new family-selection rule.
- **SPECIFIC:** ordinary access requires an explicit share.
- A copied URL does not grant access; visitors must authenticate and have permission.
- Secret Santa wishlist consent is independent and remains controlled within each event, under the existing recipient-privacy policy.

The last step is guidance, not a persisted claim that the user reviewed privacy. Returning to the dashboard neither marks a hidden completion flag nor changes privacy/shares. The guide intentionally remains resumable without analytics, dismissal storage, a tour overlay, or a new tracking table.

## Verification

`tests/Feature/Dashboard/GettingStartedTest.php` covers dashboard discovery, literal EN/PT copy, list selection and lifecycle refresh, escaped names, minimal projection, absence of purchase details/public component state, unchanged privacy and shares, guest/logout denial, and real HTTP GET/Livewire password-session revocation with current-session positives. The existing dashboard tests remain unchanged.

`tests/Browser/onboarding_smoke.py` runs the actual login, list-creation and gift-entry forms on disposable SQLite using the existing dashboard seed. Its four cases are English and Portuguese at 1280×900 and 390×844. It proves optional exit/reentry, persisted private defaults, name-only gift creation, settings navigation, resume after navigation, no automatic sharing, and denial when another authenticated user opens the copied list URL. It also checks keyboard activation of the dashboard entry, guide control bounds, horizontal overflow, private sentinels, JavaScript errors and HTTP 5xx responses, and saves screenshots plus source/asset provenance.

The browser lane is wired into the existing required PHP test job, with its own `onboarding-browser` artifact. Use the repository-qualified runtime and test isolation described in [LARAVEL_13_MODERNIZATION.md](LARAVEL_13_MODERNIZATION.md):

```bash
php artisan test tests/Feature/Dashboard/GettingStartedTest.php tests/Feature/Dashboard/DashboardTest.php
npm run build
# For older virtual CPUs: npm run build:portable
python -B tests/Browser/onboarding_smoke.py --output coverage/onboarding-browser
```

The browser command requires the pinned Playwright runtime and Chromium used in CI, and a task-owned `RUNNER_TEMP` or `TMPDIR`. It never uses a real account or application database, blocks third-party browser requests, disables external mail, and terminates its temporary server.

## Limits and follow-ups

The new guide is localized; existing creation/settings form headings, privacy option labels and some field copy still use English fallback or inline English. The missing Portuguese **Adicionar Item** label is supplied so the guide names the actual gift-entry button. The pre-existing default-avatar artwork defect remains outside this slice. This is not exhaustive accessibility, production performance, external delivery, or deployment certification. The guide resumes after navigation; it introduces no new live cross-component synchronization contract.

Exact-head hosted CI and publication state belong to the PR record; this document does not predeclare them successful. No merge or deployment is implied.
