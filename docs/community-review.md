# School support and donations — implementation review

Implemented on `v3`, reviewed against original commit `ffecf7bd1b821ab82feef6df2d43b950e2318bae`. The parent spec remains unchanged. Two independent review agents inspected the implementation: one for repository standards, one for spec coverage.

## Standards

Initial review: no documented standards violations; one optional P3 Duplicated Code finding. School and donation admin pages repeated filter parsing, pagination validation, and Kenya-local date conversion.

Resolution: both handlers now use a small shared `CommunityAdminFilters` parser while keeping their SQL local. The focused re-review confirmed the finding is resolved, with no remaining actionable standards finding.

## Spec

Initial review identified two P2 findings:

1. An asynchronous provider queue timeout prematurely moved a payment status query into terminal review instead of preserving bounded retries.
2. The QR test checked image presence without independently decoding recipient, amount, and the saved attempt reference.

Resolution: dedicated timeout handling retains the retry schedule; a test covers provider timeout followed by successful verification. The mock provider now generates a test-only QR from the actual Daraja request fields, and an independent decoder reads the displayed image and checks recipient, amount, transaction type, and reference. The focused re-review confirmed both local implementation findings are resolved.

Actual Safaricom QR payload format, merchant entitlement, supported M-Pesa app scanning, and live callback compatibility remain unverified. The mock test establishes application/provider-boundary behavior, not merchant acceptance. These are launch blockers tracked by ticket 07.

Review outcome: **0 remaining actionable local findings on each axis**. Standards: the original maintainability finding is fixed. Spec: both implementation/test findings are fixed; external launch validation remains open.

## Verification

- PHP syntax checks passed for 68 application/test files; new JavaScript test/helper syntax checks passed.
- School browser tests cover empty/configured showcase, all request fields, invalid input, CSRF, replay, intentional new requests, private admin access, normalization, notification state, search/type filters, pagination, escaping, and throttling.
- Email worker tests cover atomic request/outbox persistence, rollback, disabled configuration, stable retry keys/payloads, backoff, concurrent lease exclusion, and expiry into review.
- Donation browser tests cover direct receipts, duplicate/conflicting callbacks, untrusted/forged headers, shared-merchant attribution, date filters, QR outages/retries, independent mock QR decoding, private payment status, and admin attempts/transactions.
- Reconciliation tests cover delayed verification, duplicate processing, bounded retries, incomplete/untrusted results, asynchronous timeout recovery, reviewed statement imports, and linked full reversals.
- Desktop/mobile screenshots were inspected. The booking suite verifies navigation and absence of horizontal overflow at 320, 390, 768 and 1440 pixels.
- An obsolete booking assertion expected no header navigation, although the original v3 already had links. It was replaced with assertions for the approved school/donation links. The targeted booking rerun passed.
- Final full-suite result: **all 19 available suites passed** (`npm test`, exit 0).

No real email or money was sent during these checks. No deployment or other-branch propagation was performed. Production project content, recipient routing, and merchant collection remain unconfigured until owner-supplied inputs are available.

[Ticket map](../.scratch/school-projects-and-donations/map.md) · [Operations and configuration](community.md)
