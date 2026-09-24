# 03 — Booking details and feedback

**What to build:** A signed-in administrator can open a booking, review its contact and visit information, and read the associated feedback in context without editing any records.

**Blocked by:** 01 — Secure login and bookings list.

**Status:** resolved

- [x] Link each list entry to protected details showing reference, visitor name, phone, email, destination, date/time, attendee count, confirmation status, and creation time.
- [x] Show historical missing email as “Not provided” and show Galana arrival, departure, and overnight guest count only when applicable.
- [x] Display feedback availability in the bookings list and associated feedback in details, including self-reported attendance, applicable rating, optional enjoyment/improvement/comments, and submission time.
- [x] Distinguish “No feedback received” from “Did not attend.” Do not present non-attendance as a poor rating or as staff-verified attendance.
- [x] Never expose feedback invitation tokens, private invitation links, queued email payloads, or authentication secrets. Escape all stored values and render comments as plain text.
- [x] Require authentication for direct detail requests, return an appropriate not-found response for unknown records, and apply the private-response protections established in ticket 01.
- [x] Provide a safe return to the originating list page. Preserve validated list state when present; this behavior must work independently of ticket 02 and preserve its filters once integrated.
- [x] Display readable Kenya-local dates and times without the timezone identifier. Details and navigation work without JavaScript, on mobile, and by keyboard with accessible structure.
- [x] Test the real PHP interface with isolated day/overnight bookings, all destinations, missing email, attended feedback, non-attendance, no feedback, hostile text, unknown records, and direct unauthenticated access.
- [x] Verify opening details neither consumes invitation tokens nor changes bookings, responses, or queued emails. No edits, resend controls, or attendance workflow are introduced.

## Answer

Implemented and verified through the real PHP browser integration suite with isolated storage. Authentication, filter and detail behavior, feedback states, and relevant security cases pass. Rollout across maintained branches is tracked in ticket 04.
