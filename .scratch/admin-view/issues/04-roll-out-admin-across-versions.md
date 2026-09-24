# 04 — Roll out across all maintained versions

**What to build:** The complete admin experience works consistently in the combined PHP application and each maintained standalone version, while the static design preview remains free of private functionality and data.

**Blocked by:** 02 — Search and filter bookings; 03 — Booking details and feedback.

**Status:** resolved

- [x] Integrate all admin slices into main, v1, v2, v3, and design-preview source, committing corresponding changes to each actual branch while preserving unrelated work.
- [x] Combined deployments expose one shared admin area for their configured database, and each standalone deployment exposes the same behavior for its own database. Do not imply aggregation across separate databases.
- [x] Verify a filtered, paginated list can open details and return with its search, destination, date range, and page preserved. This is the integration check joining tickets 02 and 03.
- [x] Run the real PHP integration coverage for login, protected listing/details/feedback, filtering, logout, failed logins, and expired sessions; verify availability on each standalone version and the combined application.
- [x] Verify responsive layout, keyboard navigation, accessible controls/errors, and no-JavaScript operation across the complete workflow.
- [x] Run relevant existing booking and feedback regressions and confirm admin browsing does not mutate domain records or trigger emails.
- [x] Verify static exports exclude admin functionality, credentials, databases, real visitor records, and feedback tokens, including deployment under a repository subpath. Do not present static hosting as supporting authenticated PHP administration.
- [x] Provide operator guidance for private credential provisioning/rotation, HTTPS and trusted-proxy configuration, session expiry, persistent throttle storage, and the read-only scope. Do not create live credentials or activate production services as part of this ticket.
- [x] Record verification results and branch commits. No public registration, booking mutation, email operations dashboard, or unrelated visitor-facing redesign is added.

## Answer

Implemented in all five maintained branches. The complete admin browser suite passes in the combined application and all standalone versions. All regression suites pass; two older booking suites required updates for the previously removed destination dropdown and passed on targeted rerun. Static previews remain backend-free. Review findings and mobile reference wrapping are resolved. See the admin implementation review and operator guide for verification and configuration.
