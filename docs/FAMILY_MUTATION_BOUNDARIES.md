# Family mutation boundaries

Family code admission, settings and roster actions retain their existing entry-point policies and now recheck mutable eligibility inside the final database statement. This slice changes no schema, dependencies, invitation delivery, routes or page layout.

## Contract

- Code admission uses a conditional `INSERT … SELECT`. The family must still exist, remain active and have the submitted code; the authenticated user must still exist. The unique family/user constraint prevents duplicate admission from replacing an existing role or join timestamp.
- Updating settings, archiving a family and rotating its invite code require an active family and current creator ownership or an `ADMIN` membership in the final `UPDATE`.
- Removing or promoting a member uses the same current authority condition in the final `DELETE`/`UPDATE`, together with the exact membership ID, family ID and user ID resolved by the scoped lookup. Removal also excludes the current creator, including an ownership change after lookup. Existing self-removal refusal remains in place.
- Creator privileges do not depend on a membership row. Valid administrators, unchanged settings submissions and promotion of an existing administrator retain their previous successful behavior.
- Drivers may report zero changed rows for a valid no-op. Settings updates and promotions accept that result only when a read on the write connection confirms the same target identity, current authority and requested values. This confirmation never retries the write or overwrites intervening changes; missing, rebound, archived or newly unauthorized targets remain conflicts.
- Existing authorization and scoped-lookup failures keep their 403/404 behavior. A request that passes those checks but loses eligibility before the final statement returns 409 and sends no success notification. Reload and re-evaluate the current state rather than blindly retrying a rejected mutation.
- Email invitation acceptance/cancellation continue through their existing service and are not replaced with code-based admission.

## Regression evidence and limits

`tests/Feature/Family/FamilyMutationBoundaryTest.php` performs real SQLite writes through single-hit `Connection::beforeExecuting` hooks immediately before the protected statement. It covers code rotation, archival, deleted family/user, duplicate membership, administrator demotion/revocation, creator replacement and rebound membership identity. Each hook must fire, and the protected row must remain absent or unchanged. These are controlled statement-boundary interleavings, not multiprocess races or qualification of other database dialects.

`tests/Feature/Family/FamilyMutationHttpTest.php` uses a real authenticated page GET, its signed Livewire snapshot and the HTTP update endpoint. Positive creator/administrator cases accompany revoked-administrator denials, preserving the session middleware boundary. The existing management and resource-authorization tests remain applicable.

`tests/Feature/Family/FamilyMutationNoopTest.php` executes real SQLite statements with a narrowly scoped PDO statement test double that reports zero for the protected update. It proves unchanged settings and already-admin promotion for creators and administrators, and checks that identity, authority and desired-value drift before confirmation produce a conflict without another write or success notification. This isolates the zero-row result contract; it is not native MySQL/MariaDB execution or cross-dialect concurrency qualification.

`tests/Browser/family_mutations_smoke.py` exercises settings persistence, code rotation, rejection of the previous code, admission with the current code, promotion, removal and denial of a mounted join form after archival. It runs in English and Portuguese at desktop and narrow-mobile widths, with ephemeral accounts, loopback-only requests, a run-owned database, array mail and server teardown. This is functional browser coverage, not a complete localization or responsive-layout audit of the pre-existing family views.

Run the focused contracts with:

```sh
php artisan test tests/Feature/Family/FamilyMutationBoundaryTest.php tests/Feature/Family/FamilyMutationHttpTest.php tests/Feature/Family/FamilyMutationNoopTest.php tests/Feature/Family/FamilyManagementTest.php tests/Feature/Family/ResourceAuthorizationTest.php
python -B tests/Browser/family_mutations_smoke.py --output <task-owned-evidence-directory>
```

The browser command requires the locked application dependencies, built Vite assets, Playwright 1.65.0 with Chromium, a compatible PHP CLI, and `RUNNER_TEMP` or `TMPDIR` pointing to disposable task storage. CI runs it after the full test/coverage and release boot gates and retains the four-case report and screenshots. Publication results are reported on the PR for its exact head; defining these gates is not a claim that they have already passed.
