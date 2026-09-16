# 02 — Connect day bookings to PHP and SQLite

**What to build:** Visitors can submit a real individual or group day visit to any of the three destinations, correct errors without losing valid input, and receive automatic on-screen confirmation only after the booking is saved.

**Blocked by:** 01 — Build the modern, professional responsive UI prototype, including explicit owner UI approval.

**Status:** resolved

- [x] Connect the approved UI to a runnable PHP application using SQLite, server-rendered HTML, CSS, and minimal JavaScript. Keep presentation, destination/schedule configuration, booking creation, and persistence responsibilities distinct and reusable for future management features.
- [x] Support Sahajanand School, Galana, and Feeding Centre day bookings through preselected destination buttons and the destination dropdown. Reject unknown destinations and slots that do not belong to the selected destination.
- [x] Require and validate full name, phone number, visit date, time slot, and positive integer attendee count including the booking contact. Support a documented, permissive Kenyan/international phone-number policy and normalize accepted input.
- [x] Use Africa/Nairobi for displayed slots and past-date validation; store creation timestamps consistently in UTC. Include Feeding Centre's 11 am slot and keep remaining provisional slots centrally configurable.
- [x] Render clear field-level errors and preserve valid input after invalid submissions. Validation must work server-side and cannot rely solely on browser controls.
- [x] Save a hard-to-guess unique reference, name, normalized phone, destination, visit date, booked time, attendee count, automatic-confirmation status, and creation timestamp. Preserve the booked time when schedule configuration changes.
- [x] Display a real confirmation summary and reference only after a successful transactional write. Mask the displayed phone number and keep personal details out of URLs. Wording acknowledges the registered visit without claiming capacity checks or guaranteed accommodation.
- [x] Use prepared statements, output escaping, CSRF protection, a server-enforced submission token, and redirect-after-success. Repeated clicks, replayed valid submissions, and confirmation refreshes create at most one booking per submission token.
- [x] Keep the database and private configuration outside the public document root. Provide repeatable initialization and migrations without destroying existing records.
- [x] For this slice, real booking submission supports day visits only. Mark overnight submission as not yet available rather than presenting a mock overnight request as saved; ticket 03 enables it.
- [x] Establish browser-level end-to-end tests against the real PHP app and isolated temporary SQLite database, with the test runner development-only. Cover every destination, group booking, invalid inputs, slot tampering, correction, confirmation, and duplicate prevention.
- [x] Verify bookings survive an application process restart. Use database inspection only as a test persistence assertion, not through a new public retrieval endpoint.
- [x] Do not introduce ID collection, visitor accounts, approval workflows, outbound messages, admin screens, exports, payments, or invented capacity limits.

## Scope notes

Additional verification: nine real-browser groups pass, including repeatable CLI migrations, UTC status/timestamp assertions, changed schedule configuration with historical booked time preserved, and actual validation/confirmation plus repeated clicks at 320/390/768/1440. Added private `BOOKING_SCHEDULE` override and correct minute-level labels. Reviewed narrow validation and desktop confirmation; added summary clearance for the close button. Legacy UI test entry now delegates to real-flow regressions rather than mock preview tests. Final keyboard/touch/gallery regression coverage and audit remain pending.

Verification progress: seven grouped real-browser checks now pass: all destinations/group bookings; replay/refresh/restart persistence; no-JavaScript server-rendered booking/confirmation; nine invalid or tampered inputs; CSRF recovery and escaped user text; private-file URL rejection; controlled storage failure with retained input and successful retry. The test exposed and fixed no-JavaScript scroll instability and PHP development-server fallback returning homepage responses for unknown private paths. Full UI regression, migration/schedule snapshot checks and final requirement audit remain pending.

Implementation progress: after recorded owner approval, the first browser tracer failed against the mock preview, then passed through real Feeding Centre group submission, PHP POST, SQLite write, and 303 confirmation. Added private schedule configuration, validation/storage boundaries, migrations, CSRF and submission tokens, masked confirmation and disabled overnight submission. Adversarial/replay/restart and full responsive regression verification remain pending; ticket is claimed, not complete.

Preserve the owner-approved UI. Galana day visits are fully functional here; overnight persistence is delivered by ticket 03. Storage-failure recovery and full release verification are completed in ticket 04, but this ticket must never falsely confirm an unsuccessful write.

## Answer

Implemented real PHP + SQLite day bookings for all three destinations while preserving the approved UI. Server validation, normalized phones, private schedules/storage, non-destructive migrations, CSRF/session submission tokens, prepared transactional writes, unique references and redirect-after-success provide automatic masked on-screen confirmation only after persistence. Overnight remains explicitly unavailable.

Final verification: ten grouped real-browser checks pass against isolated temporary SQLite/session storage, including adversarial correction, duplicate/replay prevention, restart persistence, schedule snapshots, migrations, failure/retry, no-JavaScript flow, responsive screenshots, keyboard/touch/gallery/reduced-motion regressions. All PHP/JS syntax checks pass. Requirement evidence is recorded in `docs/day-booking-review.md`. Ticket 03 and release/hosting work remain separate.
