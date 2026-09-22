# Post-visit feedback

Every new eligible booking contact is invited regardless of attendance. Invitations are due at 09:00 Africa/Nairobi the morning after a day visit, or the morning after departure for Galana overnight stays. Each booking receives one invitation, not one per attendee. Visitors can rate an attended visit or select “I didn’t attend.” Optional comments are limited to 2,000 characters each. Submitted responses cannot be edited or overwritten.

## Configuration

Configure the PHP web service and the existing email worker with:

- `FEEDBACK_EMAIL_ENABLED=1` to schedule and send feedback invitations. Default: disabled.
- `BOOKING_SITE_URL=https://visits.your-domain.example` as the canonical production origin, with no path, credentials, query or fragment. Links never use the incoming Host header. The PHP app must be served at the origin root; GitHub Pages previews separately support repository subpaths.
- `FEEDBACK_SEND_HOUR=9` optionally changes the local send hour to an integer from 0 through 23. The due instant is fixed when the booking is made, so later setting changes affect new invitations only.
- `RESEND_API_KEY` and `RESEND_FROM` as documented in the Resend setup guide.

Run migrations before enabling the feature. Continue running the existing `send-booking-emails` CLI worker every minute with the same private database and environment as the web service. This worker can run when either booking emails or feedback emails are enabled. No new production scheduler is created by this change.

`BOOKING_EMAIL_ENABLED` controls immediate visitor/destination messages independently. Turning feedback off pauses pending invitations and prevents scheduling for bookings made while disabled. Re-enabling sends overdue pending invitations, but does not backfill bookings made while disabled or historical bookings. Existing private links continue working while email sending is paused. Incorrect site URL/hour configuration causes a booking to fail safely before confirmation when scheduling is enabled; validate configuration before rollout.

## Links and responses

Combined application links use the originating version's feedback route; standalone versions use their local feedback route. Random 256-bit tokens are hashed for lookup. Their raw value necessarily exists in the private queued invitation payload. Keep that database and backups private, and redact feedback query strings in production access logs. Feedback pages send no-referrer, no-store, noindex and restrictive resource headers, and load no third-party assets.

A GET does not consume the token, so email scanners cannot submit feedback. CSRF-protected POST validates and saves one response per booking. Invalid tokens show a generic unavailable page; revisiting a completed token shows acknowledgement without revealing answers. No token expiry or automated data deletion is imposed. There is no public response listing or staff dashboard in this release.

## Delivery operations

Due time and retry timing are separate. Workers atomically claim a 60-second lease, commit before calling Resend, and use a lease token to fence updates from an outdated worker. API calls retain the existing 15-second timeout. The payload, sender, link and idempotency key remain unchanged across retries. First-attempt time is recorded only when delivery starts, not when a future invitation is scheduled.

Transient failures back off up to one hour. After 23 hours from the first attempt, jobs move to `review`; check Resend before resending because its duplicate-protection window is finite. `accepted` means provider acceptance, not inbox delivery. Worker aggregate output includes accepted, retry, review and suppressed counts. Monitor overdue pending jobs and all review jobs, not just the exit status of the latest run.

If feedback was already completed before the first attempt, the invitation is suppressed. An ambiguously attempted request retains the existing retry/review behavior rather than changing its payload. Existing bookings, immediate email payloads, accepted states and provider IDs are preserved by migration. No real messages are sent by automated tests.

## Verification and preview

The feedback integration test books through HTTP, runs the worker with controlled time and mocked delivery, opens the captured link, and submits feedback. Focused worker tests cover concurrent claims, completed-response suppression and independent switches. Existing email, booking, overnight, contact and routing suites remain regression checks.

The static preview exports day and overnight samples for all three versions, linked from each preview's toolbar. Sample forms never submit, send email or save answers; reloading resets them. With JavaScript disabled, they display a limitation and keep submission disabled.
