# Honest contact availability

## Contract

The public home page does not have a contact-delivery integration. The contact section must explain that limitation before presenting any fields; it must not invite visitors to compose a message, collect one through normal controls, or claim successful delivery.

- English and Brazilian Portuguese copy identifies the unavailable service and labels each field visibly.
- A native disabled fieldset, individually disabled inputs/textarea and disabled submit button prevent normal submissions with or without JavaScript. Empty defaults replace fabricated anonymous draft values.
- The section has a labelled heading; the form is associated with its availability notice. Its single-column layout has no fixed-height heading row or narrow fractional-width button.
- Both the mounted `home.index` component and the legacy `home` alias reject `registerFeedback()` with a localized `feedback` error. A direct component call neither clears a supplied draft nor emits a success toast, queues work, sends mail, creates a notification or writes domain data.
- Newsletter state and behavior are independent of contact refusal. This slice does not implement or enable newsletter delivery, contact mail, OAuth or any external provider.
- The Portuguese features subheading is a string, not an array, so the same public page can render in that locale.

Disabled markup is a usability boundary, not authorization. The server action remains explicitly non-delivering if called directly. It is not a new server-side contact inbox, consent ledger or retention policy; no delivery or privacy-compliance claim is made.

## Regression and browser evidence

`ContactAvailabilityTest` exercises both aliases in both locales, with an empty or unrelated newsletter field, and observes the lack of domain writes and outbound dispatch. `ContactPresentationTest` checks semantic labels, native disabled controls, empty defaults and the actual mounted public route. `IndexPageTest` retains the page-level assertions with accurate copy.

The focused local suite passed **17 tests / 234 assertions** after the intended pre-fix action/presentation failures. Scoped Pint and the portable production asset build passed. These are not browser-rendering evidence.

The first hosted run reached an unrelated pre-existing assertion in `AssignmentQueueTest`: a randomly generated apostrophe-containing name was correctly HTML-escaped by Blade, but the test expected its raw spelling. A deterministic apostrophe/markup fixture reproduced that failure. The corrected test requires escaped names and rejects raw markup for named delivery; anonymous delivery rejects both raw and escaped recipient names, including the stale-visibility mail check. No mail implementation or escaping was changed. The expanded focused rerun passed **29 tests / 331 assertions** through the resource guard; final hosted qualification remains required.

`tests/Browser/contact_smoke.py` defines fresh Chromium contexts for EN/PT-BR, light/dark and 320/390/1280-pixel widths, plus JavaScript-disabled dark/mobile requests in both locales. It checks served built CSS, labels, empty disabled controls, bounded contact-section geometry, absence of application JavaScript/HTTP errors, unchanged domain counts and a bounded no-POST observation after a real pointer click. JSON records carry source/blob/asset digests, per-checkpoint geometry and screenshots; bootstrap logs and credentials are not published. The test records wider-page overflow separately: unrelated navigation/hero layout is outside this slice's acceptance boundary.

The local resource guard rejected the browser invocation before execution because it could not match the declared workload to the Python child. The guard was not bypassed or changed. Local browser checks are **NOT RUN**; the required browser matrix is wired into `.github/workflows/laravel.yml` with the `contact-browser` artifact. Hosted suite/coverage, native/portable assets, static analysis, release smoke, browser results and visual review must pass on the published candidate before the PR is marked ready. Publication-time results belong in the PR; this pre-publication document does not certify them.

## Limits and integration

There is no SMTP/provider delivery, production migration or deployment in this change. Fixtures use disposable SQLite, synthetic data, isolated services and blocked third-party browser requests. Browser checks do not prove all assistive-technology behavior or a real message lifecycle.

This branch targets `master` independently. Other unmerged home/newsletter changes may overlap the same files; any composed tree must preserve both contracts and receive its own integration qualification. No merge or deployment is authorized by this report.
