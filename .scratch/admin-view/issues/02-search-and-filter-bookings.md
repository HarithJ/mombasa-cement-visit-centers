# 02 — Search and filter bookings

**What to build:** A signed-in administrator can locate bookings by reference or visitor name and narrow the list by destination and scheduled visit date, including when results span multiple pages.

**Blocked by:** 01 — Secure login and bookings list.

**Status:** resolved

- [x] Add reference/name search, destination selection, and inclusive visit-date range filters to the protected bookings list; filters can be combined and cleared.
- [x] Date filtering uses the scheduled visit date, which is the arrival date for an overnight booking, rather than every day of the stay.
- [x] Preserve filters across pagination while maintaining stable ordering and bounded pages. Fetch only the requested page through parameterized queries.
- [x] Validate destination and date inputs server-side, including reversed date ranges, and show useful errors without silently widening the query.
- [x] Show clear no-results messaging and a way to reset the filters. Retain entered valid values after validation errors.
- [x] Filtering and pagination remain usable without JavaScript, on mobile, and with keyboard navigation and accessible labels.
- [x] Exercise the real authenticated PHP interface with isolated data covering all destinations, exact date boundaries, overnight arrivals, combined filters, multiple pages, empty results, invalid inputs, and potentially hostile search values.
- [x] Verify unauthenticated filtered requests expose no records and searching does not alter bookings, feedback, or mail queues.

## Answer

Implemented and verified through the real PHP browser integration suite with isolated storage. Authentication, filter and detail behavior, feedback states, and relevant security cases pass. Rollout across maintained branches is tracked in ticket 04.
