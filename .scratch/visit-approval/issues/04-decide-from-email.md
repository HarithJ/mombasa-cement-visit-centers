# 04 — Decide from the email buttons, and decision messages

Type: feature
Blocked by: 03
Status: ready-for-agent

**What to build:** The no-sign-in decision pages and a single decision service that every decision path (here and ticket 05) uses. Per the spec's [Deciding through the email buttons](../spec.md#deciding-through-the-email-buttons) and [Messages after a decision](../spec.md#messages-after-a-decision), and [wording.md](../wording.md) §6–9.

- [ ] A decision service (e.g. `src/BookingDecisions.php`) that, inside a transaction: checks the visit-day cutoff (23:59 Africa/Nairobi on visit date), validates the reason (required for reject, 1–500 chars trimmed), records history, updates status, and queues the visitor email + SMS (confirmed / declined, first-time vs changed variants, decider contact details from their user row, never the reason) and the team decision email to every other active user (confirmed / rejected / changed, reason included). Modes: `first` (only from pending — email links) and `change` (confirmed↔rejected — admin). Never back to pending.
- [ ] If a booking becomes rejected, any queued feedback invitation for it is cancelled.
- [ ] Decision pages: GET shows the summary and the accept or reject form (no state change); POST with CSRF decides as the link's user. Done pages, status page (already decided / expired), invalid-link page. Links refuse for deactivated users or a changed credential counter.
- [ ] Visitors without email get SMS only.
- [ ] Tests: GET never changes state; accept; reject with reason rules; first decision wins; expiry; tampered/foreign/deactivated/changed-email tokens; CSRF; message contents (rejected visitor message has decider contact and never the reason; team email has the reason); feedback cancellation.
