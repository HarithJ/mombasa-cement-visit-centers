# 03 — Reliable delivery through outages and retries

**What to build:** Visitors receive their scheduled invitation even after a temporary worker or provider outage, while operators can pause feedback sending independently of booking confirmations and identify uncertain deliveries without blindly sending duplicates.

**Blocked by:** 01 — Day-visit feedback from email to saved response

**Status:** resolved

**Type:** feature

- [ ] Pick up overdue scheduled invitations after worker downtime. Repeated scheduler runs and booking replays do not create additional logical invitations.
- [ ] Introduce or complete an independent feedback enable switch. Pausing invitations leaves immediate booking confirmation delivery operational. Define and document how pausing affects newly scheduled and already pending invitations, consistently with the spec’s new-booking-only rollout.
- [ ] Use atomic claiming and bounded leases to prevent concurrent workers from processing the same invitation normally; retain stable idempotency keys and immutable payloads across ambiguous attempts or crash recovery.
- [ ] Only an actual provider attempt starts the retry window, never the original booking time or future due time. Apply bounded retries and move uncertain jobs to review before the existing provider duplicate-protection window expires.
- [ ] Before an invitation’s first attempt, suppress it if feedback is already submitted. Do not mutate an immutable request after an ambiguous attempt; handle it through the established retry/review policy.
- [ ] Keep booking confirmation and saved feedback intact during provider failures. Missing configuration produces an actionable operational failure without exposing secrets or personal data.
- [ ] Expose meaningful aggregate worker results and persistent review states. Describe provider acceptance accurately without claiming inbox delivery; document safe investigation of review items and pending work.
- [ ] Integration tests exercise due invitations through mock timeout/failure, backoff, retry and acceptance; cover simultaneous claims, catch-up, the disabled switch, completed-response suppression, and retry-window expiry with a controlled clock.
- [ ] Preserve existing booking emails, historical queue states and the new-booking-only rollout; do not bulk-mail old records. This ticket operates on generic due invitations and does not depend on overnight date calculation from ticket 02.
- [ ] Commit updates to main, each actual version branch and the retained design-preview source. Keep static export local-only. Do not run real email sends or activate a production scheduler.

## Answer

Implemented independent switches, catch-up, due-time separation, lease claiming/fencing, immutable retries, suppression before first send and review before duplicate protection expires. Controlled-clock worker tests cover overlapping claims, expired-lease takeover and stale-result counters. Populated migration tests preserve pending, accepted and review jobs without backfill.
