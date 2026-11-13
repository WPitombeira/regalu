# Secret Santa assignments and recipient privacy

This document supersedes the older product vision **for recipient visibility in Secret Santa events**. It does not change the separate policy hiding a gift buyer's identity from the wishlist owner.

## User journey

1. The event host chooses **Show the recipient after the draw** (`NAMED`, the backward-compatible default) or **Anonymous wishlist until the reveal date** (`ANONYMOUS_UNTIL_REVEAL`). Anonymous events require an explicit reveal date. The host can revise the choice/date before the draw; both are locked afterward. Other participants cannot change them. This is an event-level policy, not a family-wide default.
2. Every accepted participant chooses their own active wishlist on the event page. This is explicit consent to show that list's gift suggestions to their assigned buyer and let that buyer mark purchases, even when the list is private. No general wishlist share is created. Selecting “Do not share a wishlist” revokes this event access; archiving/deleting the list also removes its availability. Previously viewed information cannot be recalled.
3. The draw creates each assignment and queues a delivery on the `mail` queue after the transaction commits. A failed/rolled-back draw does not publish assignment jobs. The email links to `/secret-santa/{event}/assignment`, which requires login and resolves **the current user's own assignment**. It contains no recipient or wishlist identifier in the URL.
4. Buyers see priority-ordered gift suggestions, store links and purchase status. In anonymous mode, the pages and email omit the recipient's account identity, wishlist title/description, and item description/notes. Item action references are encrypted and bound to the event and buyer. The purchase's single conditional database update checks the active drawn event, current assignment, accepted buyer/recipient, selected wishlist, list ownership/activity and available item state together. Revoking access before that write prevents purchase; concurrent buyers cannot overwrite the first purchase.
5. The scheduled `secret-santa:reveal` command transitions due active drawn events to `REVEALED` and stamps `revealed_at`. Anonymous identities are shown only after both the configured date and that transition. Named legacy events without a reveal date use their event date for this lifecycle transition. Undated events remain unchanged. Re-running the command does not re-stamp already revealed draws.
6. The host can complete an eligible event and then archive it. Completion ends event wishlist access and purchases; archival also closes assignment access. [SECRET_SANTA_EVENT_HISTORY.md](SECRET_SANTA_EVENT_HISTORY.md) defines eligibility, consent removal and the separate metadata-only history.

The host does **not** gain access to everyone else's pairings. A host participating in the draw sees only their own assignment, subject to the same secrecy rules. Reveal is private to each assigned buyer, not a public pairing table. The original assignment link remains usable after reveal; this slice does not send a second reveal email.

## Limits of anonymity

This hides the **account-to-assignment association**, not all possible clues. Products, pictures, merchant URLs or previously shared wishlists may reveal personal preferences or identity. Participants are warned to remove identifying content before opting in. It does not revoke independently granted family/specific-person access or hide the roster from its authorized host. Owners see an aggregate purchase count, not individual purchased-item status, on their ordinary wishlist page; see [WISHLIST_PURCHASE_FLOW.md](WISHLIST_PURCHASE_FLOW.md) for that separate boundary and its inference limits. There is no promise of anonymity against colluding participants or a host who can deduce a pairing from the participant/exclusion structure.

## Operations and rollout

- Apply the additive migrations before serving the new code. Existing events default to `NAMED`; existing participants have no event-specific wishlist consent.
- Keep supervised queue workers listening on `mail,default` and configure working production mail transport and `APP_URL`. Restart workers after deploying changed code/configuration. Assignment jobs have three attempts, a 30-second timeout and 10/60/180-second retry backoff; stale/deleted/ineligible assignments, including completed or archived events, are skipped at execution time.
- Run Laravel's scheduler (`php artisan schedule:run` every minute, or the deployment's existing scheduler). The reveal command is scheduled every minute with overlap prevention and uses **`APP_TIMEZONE`**. It can be run manually for catch-up with `php artisan secret-santa:reveal`. A stopped scheduler delays reveal; the page fails closed rather than revealing early.
- Jobs use after-commit dispatch, **not a transactional outbox or exactly-once SMTP delivery**. A broker failure after commit may leave assignments saved without notifications; a transport retry may duplicate mail. Use failed-job monitoring and the in-app assignment route while diagnosing delivery. No delivery guarantees are inferred from local array-mail tests.
- No production deployment, mail credentials or scheduler/worker activation is performed by this implementation.

## Verification entrypoints

- `php artisan test tests/Feature/AmigoSecreto/AssignmentPrivacyTest.php`
- `php artisan test tests/Feature/AmigoSecreto/EventWishlistTest.php`
- `php artisan test tests/Feature/AmigoSecreto/AssignmentWishlistTest.php`
- `php artisan test tests/Feature/AmigoSecreto/RevealAssignmentsTest.php`
- `php artisan test tests/Integration/AssignmentQueueTest.php`

The integration lane uses a real database queue/worker and array mail transport, with commit/rollback and current-state checks. The feature lane includes HTTP/Livewire payload secrecy, session revocation, host boundaries, consent revocation, tampered references and named/anonymous/revealed states. A deterministic SQLite interleaving probe revokes each authorization condition immediately before the purchase UPDATE, after all initial reads, and verifies that the item remains unpurchased. Production SMTP and concurrency on other database engines are separate operational qualifications.
