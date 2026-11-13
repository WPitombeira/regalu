# Wishlist product-link price suggestions

## User journey

An authenticated wishlist owner can fetch a product link while adding an item. Title and validated image behavior remain available; an explicit price/currency pair appears as a **suggestion**, not a saved item or a live checkout quote. The owner must choose **Use this price**, review the form, then save.

- Applying a suggestion fills both price bounds and its currency only when both bounds are empty. A manual zero counts as an entered price. Fetching does not overwrite a manually entered name, description, price range or currency.
- Changing the product URL invalidates the previous suggestion and image on blur. The apply action also checks that the fetched URL still equals the current URL.
- Missing, malformed or ambiguous metadata leaves manual entry available. A page title/image may still be usable when its price is rejected.
- Both manual and imported prices support BRL, USD and EUR. The new-item form defaults to BRL, visibly and editably; imported prices retain their explicit currency. There is no exchange-rate conversion.
- Price inputs accept nonnegative decimal amounts through `99999999.99`, with at most two fractional digits. Zero, one-sided bounds and ranges are supported; the maximum cannot be below the minimum. An item without either price stores no currency.
- The form, review/fallback messages and price displays support English and Brazilian Portuguese. Prices show an explicit currency code, never an ambiguous dollar symbol.

The suggestion can be cached for six hours. Store prices, shipping, taxes, availability and variant selection must be checked at the retailer. Regalu does not refresh prices in the background or promise that a suggested item is in stock.

## Conservative extraction contract

The existing bounded HTML fetch is reused. No JavaScript or remote JSON-LD context is executed or fetched.

Accepted sources:

1. Exactly one JSON-LD `Product` with one `Offer`, a plain decimal `price`, and a supported `priceCurrency`. Common `@graph`, top-level arrays, type arrays and one-element offer arrays are supported.
2. An explicit `product:price:amount` + `product:price:currency` meta pair when no structured product was found.

If both sources exist, they must agree. Multiple products/offers, `ProductGroup`, `AggregateOffer`, duplicate meta amounts/currencies, unknown currencies, malformed JSON/type shapes, missing currency, negative amounts, exponents, localized number strings and fractional cents are rejected. The parser does not infer prices from prose, currency symbols or arbitrary `itemprop` tags. Numeric JSON price tokens are preserved as strings before decoding so floating-point conversion cannot round excess precision into an accepted amount.

JSON-LD processing is bounded to ten scripts, depth sixteen and 256 container nodes. Exceeding a budget rejects the price, without guessing which portion of the page to trust. Validated metadata uses the `url-meta:v2` cache namespace; title-only older cache generations are not treated as imported prices.

## Security and privacy boundaries

- Fresh wishlist authorization is checked on fetch, URL invalidation, apply, save and render. The source URL and suggested price/currency are locked Livewire properties, not client-controlled authority.
- Fetch requests retain the existing per-user ten-attempt/minute limit.
- The existing public-address resolver runs before cache lookup. Pinned cURL DNS resolution, redirect refusal, disabled ambient proxies/compression, two-second connection and five-second transfer timeouts, and the 512 KiB body bound remain in force. Synchronous OS DNS time is not a strict end-to-end request deadline.
- The parser does not add another network request. Existing image URL validation remains bounded; the preview uses `referrerpolicy="no-referrer"`. A remote image still loads in the viewer's browser, not as a server-side image proxy.
- Adding currency to ordinary and anonymous-assignment item projections does not expose owner purchase state, purchaser IDs, recipient identity or raw anonymous item IDs. The existing wishlist and Secret Santa access policies remain separate.
- No new public endpoint, provider credential, queue worker or scheduler task is introduced.

## Data rollout and rollback

`2026_11_11_000002_add_currency_to_wishlist_items` adds a nullable currency column restricted to BRL/USD/EUR, with no default or backfill. Existing price amounts retain their exact values and **unknown currency**. They display “Currency not specified” / “Moeda não informada”; historical values are not silently relabeled as BRL.

Apply migrations before serving the new code. The migration is additive, but rollback drops the currency column and loses currencies recorded by the new version. Rolling forward again cannot reconstruct those values. Take the normal database backup and coordinate code/schema rollback; do not operate the new projection code against the rolled-back schema.

## Verification

Focused feature coverage exercises conservative extraction, raw numeric precision, hostile input/budgets, cache validation, manual-value preservation, URL invalidation, locked-property tampering, stale ownership, money validation and both locales. The populated migration integration test applies, rolls back and reapplies the actual migration while checking legacy amounts and documenting currency loss on rollback.

`MetadataTransportTest` separately exercises the real pinned cURL transport with disposable loopback HTTP, redirects, oversized content and poisoned proxy settings. The price browser journey uses synthetic external catalog HTTP/DNS only: authentication, Livewire, the metadata parser, migrations and SQLite persistence remain real.

CI runs `tests/Browser/price_import_smoke.py` for English/Portuguese × desktop/mobile, covering keyboard application of a suggestion, manual preservation/fallback, zero prices, saved ordinary wishlist display and anonymous assignment display. Screenshots and machine-readable cases are uploaded as `price-import-browser`. Browser qualification is established by a passing run for the published candidate, not by the existence of the harness. This is not a compatibility guarantee for every retailer or a production deployment certification.
