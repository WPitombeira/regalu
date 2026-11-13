# Family flow localization

## Scope and behavior

The authenticated family index, create, join-by-code, settings and roster surfaces use English and Brazilian Portuguese copy. This includes empty states, member-count pluralization, validation attribute names, role labels, mutation notices, confirmation dialogs, clipboard feedback and invitation pagination accessibility labels.

- A successful settings update reports an update, not family creation; promotion reports the completed action, not only the role name.
- Clipboard feedback uses the toast listener's existing positional-array event payload, like the server-side notice producers. Livewire forwards browser event detail as listener arguments; a flat object would pass strings to the typed array listener and prevent the toast from rendering. The clipboard API behavior itself is unchanged.
- Family names, descriptions, emails, invite codes and persisted role/status enums remain data. They are not translated.
- Member actions stack below the identity on narrow screens; desktop rows retain their side-by-side layout. Names and emails can wrap without hiding the role or action controls.
- Existing authorization, invite acceptance, queue routing and archival rules are unchanged. Email invitation operations still use the existing invitation service; entering a code remains a separate flow.
- Archiving keeps the user on settings with a localized success notice; it does not automatically redirect. The browser gate verifies the notice and persisted archive flag before explicitly opening the family index and checking that the family is absent.
- Invitation pagination uses a scoped copy of the Livewire 4.4.8 Tailwind template, changing its accessible label keys without overriding pagination throughout the app.
- This is not a complete application-wide translation sweep. Unrelated shared-shell copy and other product areas remain separate work.
- No schema migration, new dependency, provider activation or deployment configuration is introduced. This independent `master`-based slice does not include the separate family mutation-boundary PR.

## Verification contract

`tests/Feature/Family/FamilyFlowLocalizationTest.php` exercises both locales through the real components and family index, including validation, literal persisted values, create/join/update/promote/remove/archive outcomes and escaped user content. `FamilyLocalizationContractTest.php` adds catalog/placeholder parity without fallback, invitation validation and lifecycle, all invitation status labels, pluralization, real pagination, and authorization/archival denial invariants. Literal expectations are independent of the application translator.

`FamilyClipboardNotificationTest.php` pins the rendered browser event's argument shape and exercises that shape through the real Livewire event listener in both locales. It is a markup/server-boundary regression, not clipboard execution evidence. The browser gate additionally requires the listener HTTP response, the visible translated toast, and the actual clipboard contents.

The browser gate is `tests/Browser/family_flow_smoke.py --output <evidence-directory>`, using the existing isolated `event_history_smoke.application()` fixture. It requires the pinned PHP runtime, installed Composer dependencies, a real frontend build, Playwright 1.65.0 with Chromium, and task-owned `TMPDIR` or hosted `RUNNER_TEMP`.

The required browser matrix is English/Brazilian Portuguese × desktop 1280×900/mobile 390×844. Each case has fresh authenticated browser contexts and a disposable SQLite database. It checks creation, validation, copy feedback and clipboard content, member counts, settings, cancel/accept confirmations, code regeneration and invalidation, joining, role controls, promotion/removal, email invitation/cancellation, pagination and archival. Mutations are checked against persisted data; UI waits use the matching Livewire action followed by the rendered state. Pagination status rows are explicitly synthetic presentation fixtures, not proof of recipient email delivery. Outbound browser requests are blocked and mail uses the array transport.

Screenshots and geometry are captured throughout the journey. Each capture immediately checks JavaScript errors and viewport overflow, including visible controls outside either edge, so a later assertion cannot obscure a layout failure. Invitation status and cancellation checks are scoped to the exact invitee's article, not the section's explanatory copy. Reports retain the source commit/tree, dirty-state flag and built-manifest digest. The workflow runs this gate as a required step and uploads the results, geometry and screenshots even after failure. These authored checks are not evidence of browser success until they actually run.

## Local checkpoint before publication

- TDD receipts cover the missing English/Portuguese copy and notices before their respective implementation changes.
- Focused PHP verification: 17 tests, 749 assertions, no failures/errors/skips; Pint and the portable frontend build passed.
- Local scoped PHPStan was not run: the resource guard refused admission with exit 75 because host load exceeded the configured threshold. The initial interpreter-wrapped command was rejected for an unrecognized workload; the direct `vendor/bin/phpstan` command reached the actual load gate.
- The local browser command was rejected by the resource guard before execution because its Python-script form did not match the declared workload. No guard policy was changed or bypassed; rendered desktop/mobile qualification remains pending hosted CI.
- Full tests/coverage, native frontend build, full Larastan, release boot/migration smoke and browser evidence are required from exact-head CI before the PR is ready. The PR body records those later results; this section is the historical pre-publication checkpoint, not a claim that pending gates passed.
