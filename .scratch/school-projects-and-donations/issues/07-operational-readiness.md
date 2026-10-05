# 07 — Verify the complete experience and operational readiness

**What to build:** The assembled v3 experience is verified from public school projects and requests through notifications, and from optional donation through payment records, with repeatable operating instructions and explicit launch evidence.

**Blocked by:** 01, 03, 06

**Status:** claimed

**Type:** feature

**Base branch:** v3

- [ ] Integrate the sections in the approved order and ensure school requests remain discoverable from the showcase. Eliminate duplicate navigation or conflicting form behavior introduced by independently developed slices.
- [ ] Verify the full school journey: approved project content, classroom/wall/both submission, saved reference, admin access, queued notification, provider acceptance, and visible failure/recovery state. Submission and admin browsing work without JavaScript.
- [ ] Verify the full donation journey: optional wording, merchant instructions, amount selection, scannable payment QR, durable attempt, provider callback, verified receipt, private status, and matching admin records/totals. Also verify direct payments without website attempts.
- [ ] Obtain sandbox evidence using the configured merchant/API access, including QR payload/scan behavior, callback receipt, admin visibility, and duplicate handling. Record unsupported sandbox capabilities accurately; any necessary controlled production verification requires separate explicit authorization before real funds move.
- [ ] Verify recovery and operational monitoring for email failure, delayed callbacks, provider outages, unmatched receipts, reconciliation, and evidenced reversal adjustments. Operators can distinguish pending/unknown outcomes from verified success.
- [ ] Review mobile and desktop layout, keyboard navigation, readable errors, image alternatives, reduced-motion behavior, and no-JavaScript fallbacks. Existing destination stories and booking controls stay accessible.
- [ ] Verify repeatable migrations against a populated v3 database preserve bookings, overnight and transport data, feedback, and previously queued email/SMS jobs and keys. Confirm backup and recovery instructions cover the new private records.
- [ ] Run the integrated new suites and relevant booking, overnight, transport, feedback, admin, email, and SMS regressions. Fix failures attributable to this work; document any demonstrated pre-existing failure rather than claiming an entirely passing run.
- [ ] Verify new private routes retain authentication, expiry/rotation, escaping, no-store behavior, and sensitive-data protections. Static previews do not expose working collection endpoints, credentials, private records, or misleading payment success.
- [ ] Finish operator instructions covering recipient/sender settings, merchant/environment setup, HTTPS callback registration, workers, monitoring, status verification, reconciliation evidence, and recovery. Routine tests send no real email and move no real money.
- [ ] Check launch inputs: approved project facts/photos and publication permission, designated notification recipient, verified merchant name/Paybill/Till and reference convention, Daraja entitlements/credentials, callback host, and worker operation. Never substitute existing booking contacts or phone numbers by assumption.
- [ ] Record completed evidence and remaining external blockers. Leave this ticket unresolved if required launch inputs or acceptance evidence are missing; continue all independent verification. Completion describes verified readiness, not deployment approval or permission to change other branches.
