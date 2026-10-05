# 07 — Verify the complete experience and operational readiness

**What to build:** The assembled v3 experience is verified from public school projects and requests through notifications, and from optional donation through payment records, with repeatable operating instructions and explicit launch evidence.

**Blocked by:** 01, 03, 06

**Status:** needs-info

**Type:** feature

**Base branch:** v3

- [x] Integrate the sections in the approved order and ensure school requests remain discoverable from the showcase. Eliminate duplicate navigation or conflicting form behavior introduced by independently developed slices.
- [x] Verify the full school journey: approved project content, classroom/wall/both submission, saved reference, admin access, queued notification, provider acceptance, and visible failure/recovery state. Submission and admin browsing work without JavaScript.
- [x] Verify the full donation journey: optional wording, merchant instructions, amount selection, scannable payment QR, durable attempt, provider callback, verified receipt, private status, and matching admin records/totals. Also verify direct payments without website attempts.
- [ ] Obtain sandbox evidence using the configured merchant/API access, including QR payload/scan behavior, callback receipt, admin visibility, and duplicate handling. Record unsupported sandbox capabilities accurately; any necessary controlled production verification requires separate explicit authorization before real funds move.
- [x] Verify recovery and operational monitoring for email failure, delayed callbacks, provider outages, unmatched receipts, reconciliation, and evidenced reversal adjustments. Operators can distinguish pending/unknown outcomes from verified success.
- [x] Review mobile and desktop layout, keyboard navigation, readable errors, image alternatives, reduced-motion behavior, and no-JavaScript fallbacks. Existing destination stories and booking controls stay accessible.
- [x] Verify repeatable migrations against a populated v3 database preserve bookings, overnight and transport data, feedback, and previously queued email/SMS jobs and keys. Confirm backup and recovery instructions cover the new private records.
- [x] Run the integrated new suites and relevant booking, overnight, transport, feedback, admin, email, and SMS regressions. Fix failures attributable to this work; document any demonstrated pre-existing failure rather than claiming an entirely passing run.
- [x] Verify new private routes retain authentication, expiry/rotation, escaping, no-store behavior, and sensitive-data protections. Static previews do not expose working collection endpoints, credentials, private records, or misleading payment success.
- [x] Finish operator instructions covering recipient/sender settings, merchant/environment setup, HTTPS callback registration, workers, monitoring, status verification, reconciliation evidence, and recovery. Routine tests send no real email and move no real money.
- [ ] Check launch inputs: approved project facts/photos and publication permission, designated notification recipient, verified merchant name/Paybill/Till and reference convention, Daraja entitlements/credentials, callback host, and worker operation. Never substitute existing booking contacts or phone numbers by assumption.
- [x] Record completed evidence and remaining external blockers. Leave this ticket unresolved if required launch inputs or acceptance evidence are missing; continue all independent verification. Completion describes verified readiness, not deployment approval or permission to change other branches.

## Comments

Local implementation, visual checks, and two-axis review are complete. All 19 available suites passed in the final full regression run, with PHP and JavaScript syntax checks also passing. This ticket remains unresolved because approved project content, the designated notification recipient, verified merchant details/API access, and real sandbox scan-to-admin evidence have not been supplied. No production collection or delivery is claimed.

Evidence: [implementation review](../../../docs/community-review.md) and [operator guide](../../../docs/community.md).
