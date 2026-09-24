# 03 — Booking details and feedback

**What to build:** A signed-in administrator can open a booking, review its contact and visit information, and read the associated feedback in context without editing any records.

**Blocked by:** 01 — Secure login and bookings list.

**Status:** claimed

- [ ] Link each list entry to protected details showing reference, visitor name, phone, email, destination, date/time, attendee count, confirmation status, and creation time.
- [ ] Show historical missing email as “Not provided” and show Galana arrival, departure, and overnight guest count only when applicable.
- [ ] Display feedback availability in the bookings list and associated feedback in details, including self-reported attendance, applicable rating, optional enjoyment/improvement/comments, and submission time.
- [ ] Distinguish “No feedback received” from “Did not attend.” Do not present non-attendance as a poor rating or as staff-verified attendance.
- [ ] Never expose feedback invitation tokens, private invitation links, queued email payloads, or authentication secrets. Escape all stored values and render comments as plain text.
- [ ] Require authentication for direct detail requests, return an appropriate not-found response for unknown records, and apply the private-response protections established in ticket 01.
- [ ] Provide a safe return to the originating list page. Preserve validated list state when present; this behavior must work independently of ticket 02 and preserve its filters once integrated.
- [ ] Display readable Kenya-local dates and times without the timezone identifier. Details and navigation work without JavaScript, on mobile, and by keyboard with accessible structure.
- [ ] Test the real PHP interface with isolated day/overnight bookings, all destinations, missing email, attended feedback, non-attendance, no feedback, hostile text, unknown records, and direct unauthenticated access.
- [ ] Verify opening details neither consumes invitation tokens nor changes bookings, responses, or queued emails. No edits, resend controls, or attendance workflow are introduced.
