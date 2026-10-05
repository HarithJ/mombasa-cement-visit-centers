# 04 — Collect direct M-Pesa donations and record receipts

**What to build:** Visitors can find optional M-Pesa payment instructions, and eligible merchant payments are automatically recorded as traceable receipts in admin. Verified donations are distinguished from unmatched or unverified receipts and counted accurately.

**Blocked by:** None — can start immediately.

**Status:** resolved

**Type:** feature

**Base branch:** v3

- [x] Add the donation section after planning and before the final visit call to action, with responsive navigation. Clearly say a visit to any of the three destinations is enough of a contribution and monetary donations are optional.
- [x] Display approved merchant name, Paybill/Till, type, and account/reference instructions from configuration. Allow donation without a booking or school request. Never collect an M-Pesa PIN or invent recipient details.
- [x] Keep credentials and environment selection private. Unsupported, missing, or incomplete merchant configuration leaves payment collection unavailable with honest copy; implementation remains testable using fixtures.
- [x] Verify the applicable current Daraja C2B contract and document merchant onboarding, HTTPS callbacks, reference conventions, and supported source-validation controls. Do not claim an unimplemented or unsupported webhook signature mechanism.
- [x] Receive provider notifications through dedicated session-independent endpoints. Validate method, content type, size, schema, merchant/environment, amount, receipt, and timestamps. Persist accepted events durably before the provider-contract acknowledgement; storage failure must not falsely acknowledge success.
- [x] Establish callback trust through provider-supported controls at trusted ingress. When authenticity cannot be established, store the event as unverified and exclude it from confirmed donations until provider verification or reconciliation is available. Malformed or wrong-merchant events never create verified donations.
- [x] Persist private transaction and event records with receipt uniqueness scoped to merchant/environment, exact integer monetary units, currency, actual amount, references, merchant, provider time, received time, available payer data, verification state, and processing history. Missing or hashed payer information must remain accurately represented.
- [x] Deduplicate concurrent/replayed callbacks. Conflicting receipt payloads preserve verified facts and surface a review state. Only verified attributable transactions count as donations; amount alone is insufficient attribution.
- [x] Support dedicated donation merchants and shared merchants with an approved donation reference convention. Dedicated-merchant direct payments need no website attempt; insufficiently attributable shared-merchant receipts remain visible as unmatched and excluded from donation totals.
- [x] Provide authenticated donation transaction list/details, search by reference/receipt, inclusive labelled date and state filters, stable pagination, and filtered verified counts/totals. Protect every route using existing admin policies; escape private data and display Kenya-local times.
- [x] Test the public section, actual callback HTTP handlers, and admin results using isolated storage and representative provider fixtures. Cover success, replay/concurrency, malformed or untrusted inputs, merchant/environment mismatch, absent payer details, shared-merchant attribution, conflicting receipts, and persistence failure before acknowledgement.
- [x] Verify additive migrations on populated storage and existing booking/admin behavior. Document callback configuration and processing monitoring. QR initiation and advanced reconciliation are later slices; do not expose a payment QR placeholder as functional.

## Answer

Implemented optional donation copy and merchant instructions, bounded C2B callbacks, explicit source verification, idempotent receipt/event persistence, attribution, and protected transaction lists/totals. Mocked HTTP tests pass for direct, duplicate, conflicting, untrusted and shared-merchant receipts. Merchant registration and ingress validation remain launch inputs tracked by 07.

Evidence: [implementation review](../../../docs/community-review.md) and [operator guide](../../../docs/community.md).
