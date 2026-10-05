# 02 — Submit school requests and review them in admin

**What to build:** Anyone can request a classroom, school wall, or both, receive a saved-request reference, and have the complete request available to authorized staff in the existing admin. Every saved request also retains durable notification intent for subsequent delivery.

**Blocked by:** None — can start immediately.

**Status:** claimed

**Type:** feature

**Base branch:** v3

- [ ] Provide an independently discoverable school-request form without requiring a booking, donation, or account. If the showcase is available, integrate the entry point into it; its absence does not block a usable form.
- [ ] Require contact name, relationship to school, valid email, normalized phone, school name, county, town/locality, support type, and need description. Apply the parent spec's bounds and existing phone policy. Collect no pupil identities, IDs, or uploads.
- [ ] Validate server-side, preserve correctable input, and show accessible field errors. Apply CSRF protection, request-size bounds, persistent throttling, and the established trusted-proxy policy.
- [ ] Save normalized fields, a random reference, creation timestamp, and submission-token digest in private storage. Repeated clicks and replayed submission tokens return one saved request; redirect after success and keep confirmation session-bound.
- [ ] Explain that receipt is for consideration and does not guarantee construction. Storage failure must preserve input and never show a successful submission.
- [ ] Save notification intent with a stable idempotency key in the same transaction as the request, including when email configuration is missing. A failure to persist that intent rolls back the submission. Do not create dummy bookings. Delivery is implemented in ticket 03.
- [ ] Add protected school-request navigation, list, and detail views using existing admin authentication. Show all fields, reference, creation time in Kenya local time, and an honest pending/configuration-required notification state.
- [ ] Search reference and school/contact name; filter support type and inclusive submission dates. Provide stable newest-first ordering, 25-row pagination, valid empty states, and correct handling of unavailable records and invalid filters.
- [ ] Apply authorization to every private route, no-store/noindex, escaping, and parameterized queries. Public request and admin flows work on mobile, with keyboard input and without JavaScript. Browsing must not trigger notifications.
- [ ] Verify each support type, input failures, normalization, CSRF, throttling, replay/refresh, write failure, request/outbox atomicity, unauthorized access, search/filter/pagination, and escaped hostile input through the real PHP app with temporary storage.
- [ ] Apply additive repeatable migrations that preserve existing bookings, feedback, and queued messages. Run relevant admin and booking regressions; no real email is sent by this slice.
