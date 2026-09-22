# 02 — Feedback after Galana overnight stays

**What to build:** A Galana overnight booking receives its feedback invitation the morning after scheduled departure, with the stay dates shown in the email and feedback form. Its visitor can submit feedback through the same private-link journey whether or not they attended.

**Blocked by:** 01 — Day-visit feedback from email to saved response

**Status:** resolved

**Type:** feature

- [ ] For a new Galana overnight booking, schedule the invitation the morning after departure in Africa/Nairobi, using the same configurable hour as day invitations. Do not schedule from arrival or the original visit date.
- [ ] Single-night and multi-night stays receive one invitation per booking, independently of attendance. Do not generate a second day-visit invitation for the same booking.
- [ ] Show the correct destination and stay dates in the invitation and private form; preserve the originating design and the attended/non-attended response paths.
- [ ] Unchecked or non-Galana overnight fields do not affect day-visit timing. Existing day-visit scheduling and immediate confirmation emails continue to work.
- [ ] Integration tests follow a Galana booking through the worker, invitation link and saved response. Cover exact due-time boundaries, calendar/month/year transitions, single-night and multi-night departures, and no send before the due instant.
- [ ] Use temporary storage, a controlled clock and mocked Resend; no live email or elapsed-time sleeps are needed.
- [ ] Commit updates to main, the actual v1/v2/v3 branches, and retained design-preview source, with relevant regression checks. Do not activate production delivery.

## Answer

Implemented departure-based scheduling and stay-date context for Galana overnight bookings. Integration checks cover a multi-night stay across a year boundary, exact due time and non-attendance without JavaScript. All version branches are synchronized.
