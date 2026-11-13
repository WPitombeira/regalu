# Wishlist form localization

## Scope

The wishlist creation and owner settings forms use dedicated `wishlist_forms` catalogs for English (`en`) and Brazilian Portuguese (`pt_BR`). The translated surface includes headings, labels, placeholders, type/privacy option labels, empty states, sharing errors, validation attribute names, success notifications, archive/delete confirmations, access badges, and accessible names for sharing controls.

This is a copy and accessibility-label change, not a new sharing policy or layout redesign. The existing application locale selects the catalog. This slice does not add a language switcher, persist a user language preference, or negotiate the browser language. Browser qualification sets `APP_LOCALE` explicitly for each disposable application.

## Preserved contracts

- Wishlist creation still defaults to `PRIVATE` and `GENERIC`; submitting a family does not implicitly change privacy or grant a share.
- Submitted/stored enum values (`PRIVATE`, `FAMILY`, `SPECIFIC`, `GENERIC`, `CHRISTMAS`, `BIRTHDAY`, `WEDDING`, `VIEW`, `EDIT`) are not translated.
- User-authored wishlist names, descriptions, family names and shared-user data remain unchanged and escaped when rendered.
- Validation rules, field names, routes, redirects, event payload shape and authentication/authorization checks are unchanged. Validation messages use the existing framework catalogs with localized field names.
- Adding a share still creates `VIEW` access. Existing `EDIT` rows receive a translated badge only; they do not grant editing access. Settings and mutations remain owner-only and reauthorize fresh state.
- Missing-user, self-sharing and duplicate-sharing errors do not create access. Removing an existing share revokes the recipient's ordinary list access.
- Native confirmation dialogs remain required for the UI's archive/delete actions. Dismissing either dialog leaves the list unchanged.
- No migration, dependency update, external translation service or new persistent setting is introduced.

Item entry/import, list-detail copy, the global shell and notification component chrome are outside this slice. The legacy `EDIT` badge is retained rather than treated as a new capability.

## Verification

Run the scoped localization and existing authorization regressions:

```sh
php artisan test --compact tests/Feature/Wishlist/WishlistFormLocalizationTest.php tests/Feature/Wishlist/WishlistAuthorizationTest.php
```

The feature tests use reviewed literal copy for both locales, verify scoped key/placeholder parity without relying on fallback, exercise validation and lifecycle notifications, preserve persisted enum/user-data values, check escaped accessible labels, and reject both ordinary viewers and legacy editors from settings.

Build assets with the repository's qualified runtime, then run the real browser journey using the isolated Playwright runtime:

```sh
npm run build:portable
python -B tests/Browser/wishlist_forms_smoke.py --output coverage/wishlist-forms-browser
```

The browser matrix is `en`/`pt_BR` × desktop (1280 × 900)/mobile (390 × 844). Each case uses real authentication and Livewire requests against a newly migrated, disposable SQLite application. It covers private creation, validation errors, updating privacy, sharing and revocation with a second authenticated user, keyboard activation of the remove control, owner-only settings, and cancelled/accepted archive/delete confirmations. It checks persisted state, localized rendered copy, viewport bounds and JavaScript errors. All third-party requests are blocked; no external message delivery is claimed.

Screenshots are taken after rendered-state assertions and after already-asserted success toasts have naturally hidden, so transient notifications do not cover the form. The JSON report records source and asset identities plus the result of every case. The CI workflow runs this journey and uploads the `wishlist-forms-browser` artifact alongside the existing browser and PHP reports.

The full qualification also includes Pint, Larastan, dependency audits, the complete coverage suite, native/portable asset gates and a disposable release cache/migration/rollback smoke. A portable local build is not evidence for the native build, and a locally resource-blocked gate is not a pass. Exact-head hosted results and independent source-review status are recorded in the pull request rather than inferred from this procedure.
