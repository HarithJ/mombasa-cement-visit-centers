# School support and donations

These features extend **v3**. School requests, notification jobs, donation attempts, receipt events, status queries, and reversal adjustments share the existing private SQLite database. Only the public directory may be served. New migrations are additive and repeatable; existing bookings and their notifications are independent.

## Public school projects

Maintain the approved collection in `config/school-projects.php`. Each entry has `id` (stable unique slug), `school`, `location`, `type` (Classroom or School wall, or both), `description`, and `photos` (each with a local `src` and descriptive `alt`). Optional `completed` and `quantity` are verified display text. Keep the same photo privacy treatment as the existing site and optimize copies under public assets. Do not publish invented projects or repurpose school photographs as construction evidence. Missing content displays an honest empty state; there is no admin CMS.

`SCHOOL_PROJECTS_CONFIG` can select another private, trusted PHP content configuration, following the existing schedule configuration convention. Never point it at a requester-controlled upload. Tests use explicitly fictional fixtures; the default production collection is empty pending owner-approved content.

The request form is at `/school-request`, linked from the showcase. Staff use `/admin/school-requests`. Each request saves a durable email intent atomically. Confirmation is session-bound; a refresh or replay cannot create another record. Fields and personal information remain private. Submissions are for consideration, not construction approval. Times in admin are Kenya local time; date filters use inclusive Kenya calendar dates.

## School notification delivery

Configure privately in the web and worker environments:

| Setting | Meaning |
| --- | --- |
| `SCHOOL_EMAIL_ENABLED=1` | Enable school notifications independently of booking mail |
| `SCHOOL_NOTIFICATION_TO` | Comma-separated approved staff emails; no automatic manager override |
| `RESEND_API_KEY` | Private Resend key |
| `RESEND_FROM` | Verified plain sender address |
| `COMMUNITY_BASE_URL` | Canonical HTTPS site origin, used for authenticated admin links and M-Pesa status callbacks |
| `BOOKING_DB` | Existing private database path |

Run `php bin/send-school-emails.php` every minute under the application account. It processes at most 25 jobs. Missing configuration keeps notification intent pending and appears as **Configuration required** in admin. Setting up delivery later sends eligible pending jobs. Once sending begins, the recipients, sender, message, and idempotency key remain stable, even if configuration changes.

The worker leases a message for 60 seconds, uses the existing provider timeout, and backs off up to an hour. Ambiguous delivery older than 23 hours enters review to avoid retrying beyond Resend's idempotency window. Admin **Provider accepted** means Resend accepted the request, not inbox delivery. Check Resend before any operational resend. Browsing admin sends nothing. Aggregate worker output and exit status identify retry/review/configuration problems; private payloads are never printed.

## Merchant setup

No merchant details are supplied by this implementation. Until an approved merchant is configured, the public site explains that donations are unavailable. Do not use a destination contact phone as an inferred payment number.

| Setting | Meaning |
| --- | --- |
| `MPESA_ENABLED=1` | Enable the merchant integration |
| `MPESA_ENVIRONMENT` | Exactly `sandbox` or `production` |
| `MPESA_MERCHANT_NAME` | Approved displayed recipient |
| `MPESA_MERCHANT_TYPE` | `paybill` or `till` |
| `MPESA_SHORTCODE` | Merchant code in C2B notifications and transaction queries |
| `MPESA_PAYMENT_NUMBER` | Public Paybill/Till and QR credit-party identifier; defaults to shortcode |
| `MPESA_DEDICATED` | `1` only for a merchant dedicated to donations; otherwise `0` |
| `MPESA_DONATION_REFERENCE` | Approved direct-payment account reference; required for Paybill and shared merchants |
| `MPESA_QR_ENABLED=1` | Enable provider-generated payment QRs after merchant entitlement is verified |
| `MPESA_CONSUMER_KEY`, `MPESA_CONSUMER_SECRET` | Private Daraja app credentials for the chosen environment |
| `MPESA_MAX_DONATION_KES` | Positive whole-shilling upper bound, default 250000; operator must set this within current merchant limits |

Shared tills without a reliable donation reference are intentionally unavailable: a receipt alone cannot distinguish donations from other business. A dedicated till can record verified direct receipts as donations. If its QR references do not survive into C2B notifications, those donations still appear in the ledger but cannot be reliably matched to individual website attempts. Confirm this merchant capability before enabling the QR flow. Never match by amount alone.

Generate QR through `/donate`. Amounts are positive whole shillings; ledger amounts are exact minor-unit integers. The server calls Daraja's dynamic QR endpoint and displays its validated PNG, recipient, amount, and reference. The site does not collect a PIN, initiate STK Push, or claim success merely because a QR exists. QR retries recover the same attempt. A status refresh reads persisted receipts; no automatic charge is made. For a same-device donor, readable Paybill/Till instructions remain available. Supported app scanning and QR reference behavior require merchant sandbox evidence.

Sandbox pages are labelled. Sandbox records remain visible in admin but never contribute to production donation totals.

## C2B notifications and trust

Register `https://<site>/mpesa/confirmation` as the merchant's confirmation URL through the approved Daraja onboarding flow. This application does not enable optional C2B pre-validation or register callback URLs automatically. It validates notifications and durably records events before returning the provider acknowledgement. Invalid merchant/schema returns 400, unsupported method 405, storage failure 503 for retry, and oversized requests 413. Acknowledging an unverified notification does not mark the payment received.

Configure **one** documented source-verification arrangement:

- `MPESA_TRUSTED_CALLBACK_IPS`: exact direct peer IPs approved by Safaricom for this environment. Do not populate this list with a general web proxy address. Do not copy arbitrary IP lists from examples.
- When using a trusted ingress that independently authenticates Safaricom traffic, set exact ingress peers in `MPESA_CALLBACK_PROXY_IPS` and a random private value of at least 32 characters in `MPESA_INGRESS_TOKEN`. The ingress must strip any incoming `X-Nyumba-Mpesa-Verified` header and inject this value **only after source verification**, and prevent direct origin bypass. Merely supplying the header cannot authenticate an external request.

If neither arrangement verifies the source, receipts are stored as **Unverified** and excluded from totals until independent provider verification or reviewed merchant evidence resolves them. Neither browser sessions, proxy-forwarded client IPs, nor a user-pasted receipt are proof of payment. Confirmation and result endpoints accept bounded JSON, rate-limit direct peers, and never use browser CSRF. No provider signing mechanism is assumed.

Receipt uniqueness is scoped by environment and merchant. Repeat events are idempotent. Conflicting trusted events require review without overwriting established facts. Untrusted conflicting events cannot downgrade or rewrite a verified receipt. Dedicated merchant payments count once verified; shared merchant receipts require the approved reference or an existing donation-attempt reference. Unknown references remain unmatched. Amount discrepancies need review rather than falsely completing the website attempt.

## Admin and verification recovery

`/admin/donations` shows transactions, amounts, source history, verification state, search, inclusive transaction-date filters, and verified production totals. `/admin/donations/attempts` shows attempts, creation-date filters, QR state, payment state, and links to matching receipts. All use existing admin login/session protection. Unknown or unverified outcomes never inflate totals.

For scheduled provider checks configure:

- `MPESA_STATUS_ENABLED=1`.
- `MPESA_INITIATOR` and `MPESA_SECURITY_CREDENTIAL`: the approved API operator and its encrypted security credential, with the transaction-status role.
- The consumer credentials, `COMMUNITY_BASE_URL`, and callback trust configuration above.

Run `php bin/check-mpesa-payments.php` every minute. The worker discovers unverified/conflicting receipts and checks known receipt identifiers. It saves each query before contacting the provider and accepts asynchronous results at `/mpesa/result/<opaque-run-token>`. Configure ingress for these paths as well; redact run tokens in access logs. Provider acceptance of a query is not payment verification. The response must correlate with the saved provider conversation and positively establish completed status, receipt, amount, merchant and transaction time; shared merchants also require independent reference evidence. Missing, ambiguous, or unsupported fields move the query to review. Validate these field names against the merchant's current API responses before launch.

There are at most five scheduled query attempts, with backoff from five minutes to one hour. Results arriving before the query response is persisted wait for a later worker run. Unknown outcomes and insufficient evidence remain visible. QR request IDs are **not** payment originator IDs and are not queried as transactions. A website attempt with no receipt identifier cannot be discovered by this API; compare merchant statements to discover missing receipts. No status recovery operation generates another payment.

The restricted CLI `php bin/reconcile-mpesa.php` reads one JSON document from stdin (not shell arguments):

- To query a discovered receipt: `action` is `query` and `receipt` is the M-Pesa receipt. This queues provider verification; it does not confirm funds.
- To import or correct independently reviewed merchant evidence: `action` is `confirm`, `reviewed_merchant_evidence` is boolean true, `verified_by` identifies the operator, `evidence_reference` identifies the retained merchant statement/query evidence (at least ten characters), and `receipt_data` contains the confirmed C2B-shaped fields `TransID`, `TransTime` (Kenya-local YYYYMMDDhhmmss), `TransAmount`, `BusinessShortCode`, and `BillRefNumber`; payer fields are optional.
- To record an evidenced **full** reversal: use the same reviewed-evidence envelope with `action` of `reverse` and the original receipt/amount. The original must already be verified or unmatched. A linked negative adjustment is recorded once and net totals exclude the reversed receipt. This does not call a refund API. Partial reversals require further implementation and must not be represented as full reversals.

Only a trusted operator with private database access may run this CLI. Confirm/reverse means the operator has compared independent merchant evidence; a donor screenshot or submitted receipt is insufficient. Keep evidence in private operational storage and retain the audit reference. Errors do not imply success. Correcting a conflict preserves all earlier events. Reversals cannot be undone by late callback replay.

Back up the whole private database consistently, including outboxes, events, queries and adjustments, before rollout. Restore it as a unit; never reconstruct totals from attempts. Monitor aged pending notifications, unverified/conflicting transactions, query review states, and unmatched receipts. Worker exit codes and admin views supplement merchant-statement reconciliation; callbacks alone do not guarantee discovery of every payment.

## Verification and launch handoff

Run `npm run test:community`, `npm run test:donations`, and the full `npm test`. Integration tests run real PHP servers with temporary databases/sessions, fictional content and provider boundary mocks. Worker tests exercise retries, leases and durable transaction behavior. The QR fixture encodes explicitly fictional test text; it verifies image delivery, not an authorized merchant payment. Actual QR payload/scanning and callback compatibility remain sandbox acceptance requirements.

Before launch supply approved project facts/photos, publication permission, designated email recipients, an approved merchant and attribution convention, Daraja entitlements/credentials, verified callback ingress, and working schedules. Record sandbox scan-to-admin evidence and duplicate/recovery checks. Do not enable unsupported merchant capabilities. A required production-only verification must be separately authorized before moving real funds. This implementation does not deploy or propagate changes to other version branches.

Provider references: [C2B](https://developer.safaricom.co.ke/apis/CustomerToBusiness), [Dynamic QR](https://developer.safaricom.co.ke/apis/DynamicQRCode), [Transaction Status](https://developer.safaricom.co.ke/apis/TransactionStatus). Current authenticated merchant contracts and onboarding remain authoritative.
