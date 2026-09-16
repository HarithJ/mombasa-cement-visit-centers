# 04 — Verify reliability and release readiness

**What to build:** Visitors can trust both day and overnight booking outcomes, recover safely from failures, and use the finished site across devices and accessibility modes. Maintainers receive hosting-neutral instructions for running and protecting the application without selecting or deploying to a hosting provider.

**Blocked by:** 02 — Connect day bookings to PHP and SQLite; 03 — Connect Galana overnight bookings.

**Status:** ready-for-agent

- [ ] Demonstrate controlled storage-failure behavior through the real booking flow: show a safe failure message, never display false confirmation, retain recoverable valid input where practical, and allow a successful retry without duplicate records.
- [ ] Verify duplicate clicks, replayed submissions, concurrent submissions with the same token, and confirmation refreshes produce at most one saved booking. Fix gaps across both day and overnight flows.
- [ ] Verify CSRF rejection, slot/destination tampering, and escaped user-provided text. Confirm database, configuration, logs, and backups are not downloadable through the public web surface.
- [ ] Keep diagnostics useful but free of full personal details; do not expose stack traces, filesystem locations, or secrets in visitor-facing errors. Do not add public failure-injection or test-only booking retrieval endpoints.
- [ ] Complete browser-level regression tests against isolated SQLite storage for all three destinations and Galana overnight bookings, covering successful persistence and externally observable errors rather than private implementation details.
- [ ] Perform final desktop, tablet, and narrow-mobile visual QA across destination panels, forms, long validation messages, and confirmation. Preserve the approved modern, professional visual direction and correct any overflow or spacing regressions.
- [ ] Verify keyboard-only operation, visible focus, semantic labels, descriptive image text, contrast, touch controls, error announcements, reduced motion, and dialog focus behavior if applicable.
- [ ] Complete concise, truthful privacy and footer copy with supplied contact details. Clearly mark missing content rather than inventing contacts, policies, testimonials, statistics, or retention promises.
- [ ] Document PHP/runtime extension requirements, local run and test procedures, database initialization/migrations, public/private storage boundaries, environment configuration, persistent writable storage, HTTPS requirements, and SQLite-safe backup/restore procedures. Do not choose a hosting provider or change DNS.
- [ ] Record launch handoff items: approved final logos/photos/copy, exact domain, hosting, real schedule review, retention policy, and any accommodation guarantees. Provisional schedules and unresolved operational rules must not be presented as verified availability.
- [ ] Confirm no ID collection, notifications, admin dashboard, exports, payments, accounts, capacity management, or unrelated marketing features were added.

## Scope notes

This ticket verifies and completes the integrated visitor behavior rather than deferring all security or accessibility work until the end. Earlier tickets must already meet their own safeguards. Production deployment is not authorized by this ticket.
