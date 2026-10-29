# Register interest for Kibarani and Sahajanand

Status: resolved
Type: feature

## Problem Statement

The owner wants to stop taking visit bookings for the Kibarani Feeding center and Sahajanand Special School for now, while still finding out whether people actually want to visit them. Today both places are fully bookable: visitors pick a time slot, the destination team and the group managers are notified, and the visit is treated as registered. Simply hiding the two places would give the owner no signal about demand, and deleting them from the site's configuration would break the whole public site, because every page assumes all three places exist.

## Solution

Kibarani and Sahajanand stay on the homepage with their photos and descriptions, but become interest-only. Their cards carry a "Visits opening soon — register your interest" badge and a "Tell us you're interested" button. The button opens the existing booking dialog in an interest mode with a reduced set of fields. Submitting it records an interest registration, shows an on-screen thank-you with no booking reference, and emails the two group managers. Nothing is sent to the visitor or to the destination teams.

Interest registrations are stored separately from bookings. The server refuses bookings for the two places, even ones posted directly. Galana Farm remains fully bookable and unchanged.

Admins get a read-only Interest page next to Bookings. It shows per-place totals, registrations by month, a filterable list of the people who registered, and a CSV export of the filtered list.

The privacy notice drops its statement of how long data is kept and gains a line about the interest form's purpose.

## User Stories

1. As a visitor, I want to still see Kibarani and Sahajanand on the homepage, so that I know these places exist and what they offer.
2. As a visitor, I want a clear badge on those cards saying visits are opening soon, so that I don't expect to book them today.
3. As a visitor, I want a "Tell us you're interested" button on those cards, so that I can say I'd like to visit.
4. As a visitor, I want the interest form to look like the booking dialog I've seen for Galana, so that the site feels consistent.
5. As a visitor, I want the interest form to say plainly that visits aren't open yet and this isn't a booking, so that I'm not misled.
6. As a visitor, I want to enter my full name, so that the team knows who is interested.
7. As a visitor, I want to optionally enter my organization, so that a school or group can be identified.
8. As a visitor, I want to give my phone number as the required contact, so that I can be called if visits reopen.
9. As a visitor, I want my email address to be optional, so that I can register without one.
10. As a visitor, I want to choose a preferred visit date, so that the team knows when I'd like to come.
11. As a visitor, I want the preferred date limited to tomorrow through twelve months ahead, so that I can't pick a date that makes no sense.
12. As a visitor, I want to give the number of attendees including myself, so that the team understands the size of my group.
13. As a visitor, I don't want to be asked for a time slot, transport, overnight stay or a note, so that the form stays quick.
14. As a visitor, I want clear inline errors when something is missing or invalid, with my other answers kept, so that I can fix it easily.
15. As a visitor, I want the interest form to work without JavaScript, so that I can register on any device.
16. As a visitor, I want a thank-you screen summarising what I entered, so that I know my interest was recorded correctly.
17. As a visitor, I want my phone number partly hidden on the thank-you screen, so that my details aren't exposed on screen.
18. As a visitor, I want no booking reference on the thank-you screen, so that I don't mistake my registration for a booking.
19. As a visitor, I want the thank-you screen to say I'll be contacted if visits reopen, so that I know what happens next.
20. As a visitor, I want a refresh or double-click not to register me twice, so that my submission isn't duplicated by accident.
21. As a visitor, I want to be able to register again later, for example for a different date, so that I'm never turned away.
22. As a visitor, I want the privacy notice to explain what the interest form collects and why, so that I can decide whether to submit it.
23. As a visitor booking Galana Farm, I want booking to work exactly as before, so that my visit isn't affected.
24. As the owner, I want the server to reject any booking for Kibarani or Sahajanand, including ones posted directly, so that no booking slips through.
25. As the owner, I want interest registrations stored apart from bookings, so that demand data never mixes with real visits.
26. As a group manager, I want an email for each new interest registration, so that I see demand as it arrives.
27. As a group manager, I want the email to include the place, name, organization, phone, email, preferred date and number of attendees, so that I can contact the person directly from my inbox.
28. As a group manager, I want the email subject to name the place, group size and preferred date, so that I can scan registrations at a glance.
29. As a group manager, I want the email to link to the admin Interest page, so that I can see the full picture.
30. As a group manager, I want interest emails retried like booking emails, so that a temporary email-provider failure doesn't lose a notification.
31. As the owner, I want interest emails to follow the existing booking-email on/off switch, so that they send in production and stay off locally and in tests.
32. As a destination team member at Kibarani or Sahajanand, I want to receive nothing about interest registrations, so that I don't prepare for visits that aren't happening.
33. As a group manager, I want no SMS for interest registrations, so that my phone isn't filled with non-urgent alerts.
34. As an admin, I want an Interest link next to Bookings in the admin navigation, so that I can find the page easily.
35. As an admin, I want the Interest page behind the same login, session timeouts and HTTPS rules as Bookings, so that personal data stays protected.
36. As an admin, I want a card per place showing total registrations and total attendees, so that I can judge the size of demand.
37. As an admin, I want a table of registrations per place by registration month for the last twelve months, so that I can see whether interest is growing.
38. As an admin, I want a list of registrations, newest first and 25 per page, so that I can review the people who registered.
39. As an admin, I want to filter the list by place, so that I can focus on one destination.
40. As an admin, I want to filter by registration date range, so that I can look at a specific period.
41. As an admin, I want to search by name, phone or organization, so that I can find a specific person or group, including spotting repeat registrations myself.
42. As an admin, I want invalid filter values to show an error rather than wrong results, so that I can trust what I see.
43. As an admin, I want to download a CSV of exactly what my current filters show, so that I can work with the data in a spreadsheet.
44. As an admin, I want the CSV to include registration time (Nairobi), place, name, organization, full phone, email, preferred date and attendees, so that it holds everything I need to follow up.
45. As an admin, I want CSV values that look like spreadsheet formulas neutralised, so that opening the file in Excel can't run anything harmful.
46. As an admin, I want the Interest page to be read-only, so that I can't change or delete registrations by mistake.
47. As an admin, I want existing test bookings for Kibarani and Sahajanand to keep showing in Bookings, so that the bookings view doesn't break.
48. As an admin, I want Kibarani and Sahajanand to stay in the Bookings destination filter, so that I can still find those past bookings.
49. As a developer, I want reopening a place for bookings to be a small, contained code change, so that switching back is low-risk.

## Implementation Decisions

- **Places stay in the schedule.** All three places remain configured, because the schedule loader, the homepage, the booking dialog script, admin and the notification code all assume all three exist. Each place gains an interest-only status, held in code rather than a configuration switch. Kibarani and Sahajanand are interest-only; Galana is bookable. Reopening a place means changing that code.
- **Booking validation rejects interest-only places.** The day-booking validator treats an interest-only place like an unknown destination, so posted bookings fail validation server-side. Opening the booking form with a booking link for an interest-only place opens the interest form instead.
- **New interest registration module.** It validates and stores interest registrations, mirroring the day-booking validator's style and error messages. It takes the submitted form and the schedule and returns either field errors or normalised data.
  - Fields: full name (required, same limits as bookings), organization (optional, same limits), phone (required, same normalisation policy as bookings), email (optional, same validation), place (must be interest-only), preferred visit date (strict date in Africa/Nairobi, from tomorrow through twelve months ahead inclusive), and number of attendees (positive whole number, same rules as bookings).
  - No time slot, transport, overnight fields or note.
- **Schema.** A new migration adds an `interest_registrations` table with: id, a unique submission token (for replay protection), full name, organization (nullable), phone, email (nullable), place, preferred visit date, attendees (positive), and created-at (UTC). Every successful submission inserts a row; there is no de-duplication by person.
- **Manager email delivery.** Interest notifications reuse the existing email queue, worker, retry and review behaviour. The queue currently requires a booking, so the migration lets a queued email belong to either a booking or an interest registration, and adds an interest kind. Existing queued rows are preserved.
  - One message is queued in the same transaction as the registration, only for a newly created registration, and only when booking email is enabled.
  - Recipients are the group managers only. There's no destination-team, visitor or SMS message.
- **Email content.**
  - Subject: the place, number of attendees and preferred date, e.g. "New interest: Kibarani Feeding center — 12 people, 14 Dec".
  - Body: place, name, organization, phone, email, preferred date, attendees, and a link to the admin Interest page. It uses the shared Nyumba email layout.
- **Public site.**
  - Interest-only cards show the badge and the interest button.
  - The booking dialog gains an interest mode with its own heading ("Tell us you're interested"), notice line, fields and submit label ("Register my interest").
  - It reuses the CSRF and submission-token protection and redirect-after-success, and works without JavaScript.
  - The thank-you view shows the entered details with a masked phone, no reference, and a message that the team will be in touch if visits reopen.
  - The Galana flow is unchanged.
- **Admin Interest page.**
  - A new read-only route alongside Bookings under the existing admin authentication, CSRF and error handling, with a nav link next to Bookings.
  - It shows per-place cards (registrations, total attendees) and a monthly table (registrations per place by registration month in Africa/Nairobi, last twelve months).
  - The list is newest first, 25 per page, using the same pagination pattern as Bookings.
  - Filters: place, registration date range and a free-text search across name, phone and organization. Invalid filters return a 400 with an error, as Bookings does.
  - The cards and monthly table reflect the place filter; the date filter and search apply to the list and export.
- **CSV export.**
  - A sibling admin route returns the filtered list as a CSV download, with no pagination.
  - Columns: registered at (Africa/Nairobi), place, name, organization, phone, email, preferred visit date, attendees.
  - Any value beginning with `=`, `+`, `-`, `@`, tab or carriage return is prefixed so spreadsheets treat it as text.
- **Privacy notice.**
  - Remove the "How long we keep it" section entirely, including the backups line.
  - Add one statement that the interest form collects the same kinds of details and uses them only to gauge demand and contact people if visits reopen.
  - Update the notice's last-updated date.

## Testing Decisions

- **One seam: the site's HTTP interface.** Tests drive the real PHP server against a temporary SQLite database, as the existing booking and admin suites do. They assert only on what visitors and admins see and receive: pages, status codes, CSV responses and queued outbound messages. They never test internal classes directly.
- **Good tests check external behaviour.** For example: "a Kibarani interest submission shows a thank-you without a reference and queues one email to the group managers". Tests must not depend on internal method names or private structure.
- **Public site tests.** Cover interest cards and buttons for Kibarani and Sahajanand; field validation and error preservation; the date boundaries (today rejected, tomorrow and twelve months ahead accepted, one day beyond rejected); submission without JavaScript; the thank-you contents and masked phone; replay protection; rejection of a directly posted booking for each interest-only place; and unchanged Galana booking.
- **Notification tests.** Inspect the temporary database's email and SMS queues after a registration. There should be exactly one queued interest email, addressed to both group managers, none for destination teams or the visitor, no SMS, and nothing queued when booking email is disabled. Delivery itself is already covered by the email worker tests with a fake transport and needs no new tests.
- **Admin tests.** Cover authentication enforcement on the Interest page and the export; card totals and monthly counts; list ordering and pagination at 25; place, date-range and search filters; 400 responses for invalid filters; CSV columns, filtering and formula neutralisation; and read-only behaviour (no state changes from admin use).
- **Existing tests.** Several existing suites book Kibarani or Sahajanand, for example the admin test that books `feeding` through the public site and the manager-notification test that books all three places. Repoint them to Galana or convert them to interest registrations so they keep checking the same behaviour.
- **Prior art.** The browser-driven booking and admin suites (server on a temporary database, fixed clock injection for admin), the plain PHP test scripts with a `check` helper and temporary database for queue assertions, and the organization field tests, which span both styles.

## Out of Scope

- Counting clicks on the interest button, or any analytics.
- Marking, flagging, filtering or counting repeat registrations.
- Confirmation SMS or email to the person registering.
- Notifications to the Kibarani or Sahajanand destination teams, and SMS to group managers.
- Daily or weekly digest emails.
- Editing or deleting registrations from admin. Deletion requests are handled manually.
- A configuration switch for reopening places.
- Deleting existing test bookings for Kibarani or Sahajanand.
- Retention or automatic deletion of interest registrations.
- Visit guidelines or the photography rule, which return when these places reopen.
- Pushing, opening a PR or deploying. Work happens on `main` and is committed locally once the full suite passes.

## Further Notes

- The repository has no domain glossary or ADRs yet. "Interest registration" and "interest-only place" are new terms worth adding to a future glossary.
- Group manager recipients are the hard-coded manager list used for bookings. Changing who is notified means changing that list, which also affects bookings.
- Email delivery is enabled in production, so interest emails will start sending to the group managers as soon as this is deployed.

## Answer

Implemented on `main` (2026-10-29). Kibarani and Sahajanand are interest-only in code; the server rejects bookings for them and Galana Farm is unchanged. Interest registrations are stored in `interest_registrations` (migration 017, which also lets a queued email belong to an interest registration). One email per registration is queued to both group managers, behind the existing booking-email switch, with the admin link when `BOOKING_SITE_URL` is set. Admin has a read-only Interest page and a filtered CSV export. The privacy notice covers the interest form and no longer states retention periods.

Tests: new `tests/interest.test.mjs` (public form, queue, admin, CSV); existing suites that booked Kibarani or Sahajanand now book Galana Farm. Full suite passes. Not pushed or deployed.

## Comments

- 2026-10-29: After UI review, the card status moved from a badge above the button to a "Visits opening soon" label on the photo's top-left corner. The button keeps "Tell us you’re interested".
- 2026-10-29: Moved again to a plain "Visits opening soon" status line (orange clock, no pill) just above the button, bottom-left of the card, on all screen sizes.
