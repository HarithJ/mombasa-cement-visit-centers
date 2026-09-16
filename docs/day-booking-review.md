# Ticket 02 completion audit

## Requirement evidence

| Requirement | Current evidence |
| --- | --- |
| Approved responsive PHP UI with distinct responsibilities | `public/index.php` renders HTML; `src/web.php` handles HTTP/session flow; `Schedule`, `DayBooking`, `BookingStore` isolate configuration, validation and persistence. No Composer, frontend service or production Node runtime. |
| Three destinations and dropdown; reject unknown/mismatched slots | Real browser bookings for all destinations, dropdown slot reset, and tampered destination/Feeding Centre slot tests. Server validates against private schedule definitions. |
| Required contact/date/time/integer attendees; normalized phone | Invalid-field tests bypass client validation; group and international bookings pass. Persisted Kenyan phone asserted as +254712345678. README documents permissive policy. |
| Nairobi dates/slots, UTC creation, provisional configuration | Validator explicitly uses Africa/Nairobi; labels include zone; storage uses UTC `gmdate`; tests inspect creation format/status and default Feeding Centre 11 am. Private config override exercises minute-level slots. |
| Server-rendered field errors and retained input | PHP renders field values/messages, supplemented by accessible JS focus/error state. No-JavaScript booking/confirmation test passes. Nine invalid/tampered submissions and CSRF correction retain input. |
| Required persisted values and immutable booked time | Schema stores unique 96-bit random reference, token hash, name, phone, destination/date/time/count/status/UTC creation. Schedule-change test verifies historical time remains 11:00 while new selection is 11:30. |
| Confirmation only after transaction; masked phone and private URLs | Transaction returns saved record before 303 redirect. Failed-storage test asserts no confirmation, retained details and successful retry. Confirmation renders masked suffix and uses only a fixed confirmation flag in URL. |
| Prepared SQL, escaping, CSRF, server token, PRG/idempotency | Prepared statements; escaped HTML and JSON; session-bound CSRF/issued tokens; unique token constraint. Replay/refresh/repeated-click tests inspect one saved booking per token. Submitted HTML displays as text. |
| Private storage/config and non-destructive migrations | Database defaults outside public and rejects public directories; config is private; forbidden private-file URLs return 404. CLI migrations run twice with existing records retained. |
| Day-only scope | Disabled overnight checkbox states coming soon; tampered overnight POST rejected with zero records added. No overnight persistence implemented. |
| Real browser E2E, development-only runner | Playwright devDependency; tests start actual PHP with temporary SQLite/session storage, inspect DB only for approved persistence assertions, then clean their generated directory. Both npm test entries run real flow. |
| Persistence across process restart | Test terminates/restarts PHP against same private storage and refreshes the same saved reference; record count unchanged. No public lookup endpoint introduced. |
| Excluded functionality | Source/schema review: no ID/email/account/admin/export/payment/approval workflow/capacity limits/outbound messaging. |

## Regression and visual review

Ten grouped checks cover persistence, adversarial input, recovery, schedules/migrations, four viewport sizes, keyboard and touch, continuous gallery behavior and reduced motion. Screenshots of homepage, validation and confirmation at 320/390/768/1440px are generated under ignored `test-results/`. Narrow validation and desktop confirmation were reviewed; close-button summary clearance added. The original approved header/logo/photo composition is retained.

Ticket 03 handles overnight persistence. Ticket 04 handles broader release hardening. Operational schedules and hosting/deployment remain owner decisions; completing ticket 02 is not production-launch approval.
