# 03 — Deliver school-request email notifications reliably

**What to build:** A newly submitted school request results in an email to the designated staff recipient, with recoverable delivery failures and visible notification state in admin.

**Blocked by:** 02

**Status:** resolved

**Type:** feature

**Base branch:** v3

- [x] Deliver the notification intent saved alongside each school request through a dedicated privately configured recipient or recipient list. Do not inherit booking-manager overrides or destination contacts by assumption.
- [x] Use the existing Resend client and Nyumba email presentation for HTML and plain text. Include reference, school, support type, contact and need details, and an authenticated admin link. Escape submitted text and keep it out of mail headers.
- [x] Make only the adaptation needed to deliver the school-request outbox. Preserve existing booking/feedback queue payloads, provider keys, scheduling, and message types. No dummy booking records or synchronous form-request sending.
- [x] Missing or disabled delivery configuration retains unsent intent and displays a configuration-required state. Configuring delivery later makes those requests eligible without requiring resubmission.
- [x] Use immutable payloads once sending starts, stable provider idempotency keys, database leases, timeouts, and bounded backoff. Stop ambiguous retries before the provider idempotency window expires and surface review-required state.
- [x] Show pending/retrying, provider-accepted, configuration-required, and review-required states accurately in request details. Provider acceptance must not be labelled confirmed inbox delivery. Email failure never deletes or invalidates a saved request.
- [x] Test a real form submission through saved request, worker execution with a mocked sender, and admin-visible status. Cover recipient routing, missing configuration, restoration, provider failure, concurrent workers, crash/retry, duplicate prevention, and retry-window expiry with a controlled clock.
- [x] Document private recipient/sender settings, worker scheduling, monitoring, and recovery. Do not send real emails in tests or introduce automatic requester acknowledgement or SMS.
- [x] Run existing email, feedback-worker, and relevant booking notification regressions; viewing admin remains side-effect free.

## Answer

Implemented a separate school notification outbox worker using the existing Resend client and email layout. Configurable recipients, immutable retries, leases, review expiry, and admin-visible delivery state pass mocked-provider tests. The actual recipient/sender setup remains a launch input tracked by 07.

Evidence: [implementation review](../../../docs/community-review.md) and [operator guide](../../../docs/community.md).
