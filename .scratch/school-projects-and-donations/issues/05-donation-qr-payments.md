# 05 — Donate through a payment QR code

**What to build:** A donor chooses an amount, receives a genuine M-Pesa merchant payment QR and readable instructions, and can see whether the associated payment has been confirmed. Staff can trace each attempt and its matching receipt.

**Blocked by:** 04

**Status:** claimed

**Type:** feature

**Base branch:** v3

- [ ] Validate the configured merchant's supported QR API, transaction type, amount/reference fields, and callback correlation contract against current Daraja documentation. Missing entitlement or configuration disables QR collection safely and is documented as a launch dependency.
- [ ] Accept positive whole-shilling KES amounts within configured provider/merchant limits. Persist exact amounts, a random reference, creation time, submission identity, and initiation state before calling the provider. No donor phone/email, account, or booking is required to display payment instructions.
- [ ] Apply server validation, CSRF, persistent rate limits, request bounds, and submission idempotency. Repeated requests reuse the saved attempt; timeouts and safe QR retries must not imply payment or initiate a second charge.
- [ ] Display a validated provider-supported payment QR with merchant name, amount, reference, clear space, contrast, and text alternatives. The QR encodes payment information rather than just a website URL. Only show expiry behavior supported by the provider contract.
- [ ] Retain readable, copyable merchant/reference instructions for same-device donors and useful no-JavaScript behavior. Clearly distinguish QR-generation failure, awaiting confirmation, received, and needs-review states.
- [ ] Link verified receipts to attempts using supported stable references, never amount alone. Record actual received amounts; flag mismatches without falsely completing the expected attempt. Duplicate receipts never increase totals; a closed browser does not stop receipt processing.
- [ ] Expose payment status only to the originating session or an opaque capability, without public payer or receipt enumeration. Show confirmed receipt only from persisted verified evidence, with payer information masked where displayed.
- [ ] Extend protected admin views to show attempts separately from transactions, with expected amount, initiation outcome, timestamps, provider identifiers where available, matching state, and links to associated receipt/history. Pending, failed, and unmatched attempts do not affect monetary totals.
- [ ] Exercise the amount form through provider mock, rendered QR, callback, private donor confirmation, and admin record. Cover invalid amounts, no configuration, timeouts, retries, replay, wrong-session access, late confirmation, mismatches, and absent payer information.
- [ ] Decode QR fixtures and verify recipient, amount, and supported reference payload; review mobile/desktop and keyboard/no-JavaScript behavior. Record real merchant sandbox scan evidence when access exists; mock success alone does not prove production compatibility.
- [ ] Preserve direct M-Pesa recording, school requests, and bookings during provider outages. Do not add STK Push, collect PINs, or move real funds during tests.
