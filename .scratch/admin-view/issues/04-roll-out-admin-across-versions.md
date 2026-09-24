# 04 — Roll out across all maintained versions

**What to build:** The complete admin experience works consistently in the combined PHP application and each maintained standalone version, while the static design preview remains free of private functionality and data.

**Blocked by:** 02 — Search and filter bookings; 03 — Booking details and feedback.

**Status:** claimed

- [ ] Integrate all admin slices into main, v1, v2, v3, and design-preview source, committing corresponding changes to each actual branch while preserving unrelated work.
- [ ] Combined deployments expose one shared admin area for their configured database, and each standalone deployment exposes the same behavior for its own database. Do not imply aggregation across separate databases.
- [ ] Verify a filtered, paginated list can open details and return with its search, destination, date range, and page preserved. This is the integration check joining tickets 02 and 03.
- [ ] Run the real PHP integration coverage for login, protected listing/details/feedback, filtering, logout, failed logins, and expired sessions; verify availability on each standalone version and the combined application.
- [ ] Verify responsive layout, keyboard navigation, accessible controls/errors, and no-JavaScript operation across the complete workflow.
- [ ] Run relevant existing booking and feedback regressions and confirm admin browsing does not mutate domain records or trigger emails.
- [ ] Verify static exports exclude admin functionality, credentials, databases, real visitor records, and feedback tokens, including deployment under a repository subpath. Do not present static hosting as supporting authenticated PHP administration.
- [ ] Provide operator guidance for private credential provisioning/rotation, HTTPS and trusted-proxy configuration, session expiry, persistent throttle storage, and the read-only scope. Do not create live credentials or activate production services as part of this ticket.
- [ ] Record verification results and branch commits. No public registration, booking mutation, email operations dashboard, or unrelated visitor-facing redesign is added.
