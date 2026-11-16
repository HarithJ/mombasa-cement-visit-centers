# 03 — Pending bookings and approval-request messages

Type: feature
Blocked by: 01
Status: resolved

**What to build:** New bookings start pending and everyone is told so; the team email goes to every active user with personal Accept/Reject links. Per the spec's [Booking status](../spec.md#booking-status) and [Messages when a booking is made](../spec.md#messages-when-a-booking-is-made), and [wording.md](../wording.md) §1–5.

- [x] Migration `021-booking-decisions.sql`: existing `automatically_confirmed` → `confirmed` (no history rows); a `booking_decisions` history table (booking, user, decision, reason, previous status, decided_at); a `decision_links` table (booking, user, action accept|reject, token hash, created_at). New bookings saved as `pending`.
- [x] On-screen confirmation, visitor email and visitor SMS use the pending wording (§1–3).
- [x] Team email (§4): one email per active user, each with their own Accept/Reject URLs (`/decide/<token>` style — exact route is this ticket's choice, ticket 04 serves it). Only token hashes are stored. Recipients come from the users table, not `contacts.php`/`ManagerNotifications.php`.
- [x] Admin SMS (§5) to every active admin's phone. Remove `ManagerNotifications.php` once unused.
- [x] Feedback invitations are only created for `confirmed` bookings.
- [x] Admin detail page shows the new status labels (wording §10) instead of "Automatically confirmed" (just the label; decisions UI is ticket 05).
- [x] Tests: update bookings/overnight/transport/email/SMS/manager-notification/feedback suites; new checks for per-recipient links and recipients.

## Answer

Implemented in `673005f` (merged `fd4d06f`): pending status and decision tables (migration 021), pending wording, per-user Accept/Reject emails via `DecisionLinks`, admin SMS to active admins, `ManagerNotifications` removed, feedback only after confirmation.
