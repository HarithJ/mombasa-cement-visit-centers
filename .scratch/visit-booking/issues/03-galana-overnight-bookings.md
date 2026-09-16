# 03 — Connect Galana overnight bookings

**What to build:** Galana visitors can include an overnight stay in a real booking, receive clear errors for inconsistent dates or guest counts, and see the saved overnight details in their on-screen confirmation.

**Blocked by:** 02 — Connect day bookings to PHP and SQLite.

**Status:** resolved

- [x] Enable real submission of the Galana overnight checkbox and conditional arrival/departure dates and overnight guest count using the approved UI and existing booking-creation flow.
- [x] For an overnight booking, require arrival date to match the selected visit date, departure date to be later than arrival, and guest count to be a positive integer no greater than total attendees. Allow one or more nights without inventing a maximum stay.
- [x] Validate overnight rules server-side, including tampered requests, and show accessible field-level errors while preserving valid entries.
- [x] Persist overnight selection, arrival/departure dates, and guest count with the booking through a nondestructive schema migration where needed. Existing day bookings remain valid and unchanged.
- [x] When overnight is unchecked, store no overnight details. When destination changes away from Galana, hide/clear irrelevant controls and exclude overnight details server-side even if stale values are submitted.
- [x] Include applicable overnight details in the real confirmation summary. Do not imply that rooms have been allocated or accommodation capacity verified.
- [x] Preserve existing transactional writes, hard-to-guess references, CSRF protection, duplicate prevention, privacy safeguards, and successful day-booking behavior.
- [x] Extend the real-app browser tests for valid single-night and multi-night stays, missing overnight values, invalid date ranges, excessive/fractional/nonpositive guest counts, disabling overnight, changing destination, tampering, and persisted confirmation details.
- [x] Verify that invalid overnight submissions create no booking and that a corrected submission creates exactly one valid booking.

## Scope notes

Verification progress: five overnight browser groups pass, covering single-night and 45-night stays, eleven invalid/tampered field combinations with zero new records, corrected submission creating exactly one record, replay/refresh, unchecked and destination-changed requests discarding malicious stale data, and repeatable migration of a v1 day record. Added an accessible checkbox field error after the test exposed its absence. Full day regression and overnight visual/fallback audit remain pending.

Implementation progress: single-night browser tracer failed on the disabled checkbox, then passed through real Galana submission and persisted arrival/departure/guest confirmation. Added nondestructive migration 002 and database consistency triggers; day records receive overnight=0 and null details. Extended server validation and transactional writes. Inactive or non-Galana requests discard stale overnight data. Full invalid/correction, multi-night, UI and migration/day regressions remain pending; ticket remains claimed.

This captures and automatically acknowledges an overnight request; accommodation inventory, room allocation, pricing, approval, and real capacity limits remain out of scope. Use the spec's identified implementation defaults without representing them as separately agreed operational policy.

## Answer

Implemented and verified real Galana overnight requests using the approved booking flow. Matching arrival/visit dates, real later departures and positive integer guests no greater than attendees are validated server-side; nullable stay details are persisted transactionally via nondestructive migration 002. Unchecked/non-Galana requests discard stale details. Saved confirmation includes stays while explicitly avoiding room-allocation guarantees.

Final evidence: seven overnight browser groups and all ten day regression groups pass through real isolated PHP/SQLite storage. Coverage includes single/multi-night stays, eleven invalid/tampered cases, correction exactly once, replay/refresh, stale-field exclusion, v1 day migration preservation, no-JavaScript flow, and 320/390/768/1440 visual checks. The frontend design review exposed close-button overlap; a restrained separate toolbar resolves it with a regression assertion. PHP/JS syntax checks pass. Requirement audit: `docs/overnight-booking-review.md`. Release hardening and hosting remain separate.
