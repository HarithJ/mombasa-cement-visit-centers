# 0001 — Galana Farm is the only destination

Status: accepted (4 November 2026)

## Context

The site was built for three destinations: Galana Farm, Sahajanand Special School and Kibarani Feeding center. The last two were later made interest-only: visitors could register interest but not book, and admins saw those registrations on an Interest page. The owner has now asked for both places to be removed completely, leaving a Galana Farm booking site.

Both places have records: bookings made before they became interest-only, interest registrations, and possibly emails or SMS still queued for them. Dropping those rows would lose visitor contact history and feedback that cannot be recovered.

## Decision

- Galana Farm is the only destination in the schedule, contacts, map, route and photo configuration. The booking validator refuses any other destination, so posted or tampered bookings for the old places fail. The homepage, booking dialog, privacy notice, emails and SMS name only Galana Farm.
- The interest-registration feature is removed (form mode, validation, manager email, admin Interest page and CSV), because it existed only for the two removed places.
- **Records are kept, not deleted.** No migration removes rows from `bookings`, `interest_registrations`, `feedback_responses` or the message queues, and the `interest_registrations` table stays.
- Admin keeps old bookings readable. They appear in the Bookings list, detail page and CSV export labelled "Retired destination", and are counted under "All destinations". The destination filter and summary cards offer Galana Farm only.
- Migration `018-galana-only.sql` parks messages still waiting to go out for the old places: pending emails (including interest notifications) become `suppressed`, and pending SMS become `review`, each with a "destination retired" reason. Messages already sent or already under review are left alone.
- A feedback link already sent for an old booking still opens. The page reads "Your Nyumba visit" in place of the place name.

## Consequences

- Nobody can book or register interest for the removed places, and nothing more is sent about them.
- Interest registrations stay in the database but are no longer shown in admin. To read them, query `interest_registrations` directly.
- The admin view and CSV no longer say which removed place an old booking was for. The stored `destination` value still does.
- Reopening a place would mean adding it back to the configuration files, the photo manifest and the homepage destination list. The old photographs and source folders are in git history.
