# One-night overnight stays at Galana Farm

Status: ready-for-agent
Type: feature

## Problem Statement

Overnight stays at Galana Farm are always one night. The booking form still treats them as open-ended: once visitors tick "Stay overnight at Galana Farm", they see a read-only arrival date, a departure date to choose, a staying-guests count, and a hint asking for "preferred dates and number of staying guests". That suggests multi-night stays are possible, adds fields nobody needs to fill in, and carries dates and guest counts into confirmations, emails and SMS that describe something the farm doesn't offer.

## Solution

Ticking "Stay overnight at Galana Farm" is the whole overnight request. The section has no hint line, no date fields and no guests field. The server records the stay as one night: arrival is the visit date, departure is the next day, and the whole group is counted as staying. Wherever an overnight request is shown (on-screen confirmation, visitor and team emails, SMS, admin booking details), it appears as a single line saying an overnight stay was requested, with no dates or guest count. Feedback invitations still arrive the morning after the guest leaves. Stored bookings are left as they are.

## User Stories

1. As a visitor booking Galana Farm, I want to request an overnight stay with a single checkbox, so that I don't have to fill in dates I can't change.
2. As a visitor, I want the overnight option to show no extra fields once ticked, so that the form stays short.
3. As a visitor, I want no hint about choosing dates or guest numbers, so that I'm not led to expect a multi-night stay.
4. As a visitor, I want my overnight request to apply to my whole group, so that I don't have to count who is staying.
5. As a visitor, I want to untick the overnight option and book a plain day visit, so that I can change my mind.
6. As a visitor, I want the overnight option to work without JavaScript, so that I can request it on any device.
7. As a visitor, I want my on-screen confirmation to say an overnight stay was requested, so that I know the request was recorded.
8. As a visitor, I want the confirmation not to list arrival, departure or staying-guest details, so that it doesn't suggest choices I didn't make.
9. As a visitor, I want my confirmation email and SMS to say I've requested an overnight stay, without dates or guest counts, so that the message is simple and accurate.
10. As a visitor, I want the messages to say accommodation is subject to confirmation, so that I don't treat the request as guaranteed.
11. As a visitor who also requests transport, I want the messages to mention both requests clearly, so that I know both were received.
12. As a visitor, I want the feedback invitation after an overnight stay to arrive the morning after I leave, so that I'm not asked while I'm still at the farm.
13. As a destination team member, I want the team email and SMS to say the visitor requested an overnight stay, so that I know to arrange accommodation.
14. As a destination team member, I want the total number of attendees in the message, so that I know how many people may stay.
15. As a group manager, I want the same clear overnight wording in my notifications, so that every recipient reads the request the same way.
16. As an admin, I want booking details to show "Overnight stay: Requested", so that I can see the request at a glance.
17. As an admin, I want existing bookings with their original dates left untouched, so that historical records stay accurate.
18. As the owner, I want every new overnight booking stored as exactly one night, so that the data matches the farm's offer.
19. As the owner, I want the stored arrival, departure and staying-guest values filled in automatically, so that anything relying on them keeps working.
20. As the owner, I want day-visit bookings to show nothing about overnight stays, so that day visitors aren't confused.
21. As a developer, I want the one-night rule enforced on the server, so that it holds whatever the form sends.

## Implementation Decisions

- **Booking form.** The overnight section is only the "Stay overnight at Galana Farm" checkbox. The hint text, the arrival and departure date fields, the staying-guests field, their errors and their notes are removed. The script no longer syncs, validates or restores those fields. The checkbox still appears only for Galana Farm.
- **Day-booking validation.** When overnight is requested for Galana Farm, the validator no longer reads or checks arrival, departure or staying guests from the submission. It derives them instead: arrival is the visit date, departure is the visit date plus one day (Africa/Nairobi calendar dates), and staying guests equals attendees. Without the checkbox, the overnight fields stay empty as today. Any submitted date or guest values are not used.
- **Storage.** There are no schema changes. The existing overnight flag, arrival date, departure date and staying-guest columns keep being written, now with the derived values. Existing rows are not migrated or altered.
- **Wording.** One line, worded to suit each place, appears wherever an overnight request is shown:
  - on-screen confirmation: "Overnight stay: Requested";
  - visitor and team/manager emails: an overnight detail row and the scenario paragraphs, with no dates or staying-guest row;
  - visitor and manager SMS: the overnight templates;
  - admin booking details: arrival, departure and overnight-guest rows replaced by a single overnight row.

  Day bookings show no overnight line. Messages keep saying accommodation is a request subject to availability and confirmation. Team and manager messages keep the total attendee count.
- **Feedback timing.** Unchanged in code: invitations are due the morning after the departure date. With the derived departure, that is two mornings after the visit date.
- **Existing bookings.** Records with stored multi-night dates still display through the new single overnight line wherever the line is used. Their stored data is unchanged.

## Testing Decisions

- Tests check external behaviour only: what visitors and admins see, what's stored, and what messages say. They don't test internal methods.
- **HTTP seam (main).** The existing overnight browser suite drives the real PHP server against a temporary database. It covers:
  - the overnight section shows only the checkbox, with no hint, date or guests field, with and without JavaScript;
  - a ticked booking stores arrival as the visit date, departure as the next day and staying guests as attendees;
  - tampered date or guest values don't change what's stored;
  - unticking (or a non-Galana booking) stores no overnight values;
  - the on-screen confirmation and the admin booking details show the single overnight line and no dates;
  - refresh and replay keep one booking;
  - the feedback invitation for an overnight booking is due the morning after the derived departure.

  Multi-night stays and departure-date validation are removed from this suite.
- **Message seam (existing).** The plain PHP tests that build email and SMS messages for each scenario (day or overnight, with or without transport) are updated. Overnight messages must say an overnight stay was requested, must not contain arrival/departure dates or a staying-guest count, and must still say the request is subject to availability. Day messages must not mention an overnight stay.
- **Prior art:** the overnight and booking browser suites, the admin suite (logged-in booking details), the email, email-design and booking-SMS PHP tests, and the feedback suite (due-time checks).

## Out of Scope

- Stays longer than one night, or choosing a different arrival date.
- Partial-group stays or a staying-guests count.
- Migrating, correcting or deleting existing bookings with multi-night dates.
- Changes to Kibarani or Sahajanand interest registrations.
- Changes to the Galana card's layout (reverted to the overnight pill above the button; see Further Notes).
- Pushing or deploying.

## Further Notes

- Before this work, the local-only commit that moved Galana's overnight pill below the button is removed from `main`'s history, restoring the card to the pill above the button. It was never pushed.
- Work happens on `main`: update tests, run the full suite, commit locally.
- "Staying guests" in stored data now always equals attendees for new overnight bookings. Anyone reading the raw data should treat it as "the group" rather than a visitor-chosen number.
