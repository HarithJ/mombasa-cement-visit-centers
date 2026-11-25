# Visit Galana

A booking site for visits to Galana Farm, the Nyumba Foundation's agricultural project near Malindi.

## Glossary

- **Destination**: a place visitors can book. Galana Farm is the only one (see [ADR 0001](docs/adr/0001-galana-farm-only.md)). Its schedule, contacts, map link, route illustration and photographs are configured under `config/`.
- **Booking**: a registered day visit. It has a date, a time slot, an attendee count that includes the contact, an optional transport request and an optional overnight stay. A new booking is pending until a user confirms or rejects it; bookings made before visit approval count as confirmed.
- **Overnight stay**: a request, made with one checkbox, to stay one night at Galana Farm with the whole group. Arrival, departure and staying guests are worked out from the booking; they are never asked for or shown.
- **Retired destination**: a place that used to be offered and is no longer. Its bookings and interest registrations are kept, and admin lists the bookings under this label. Nothing more is sent about them.
- **User**: a staff account in the `users` table, with a name, email, phone and role (`admin` or `team`). Every active user gets new-booking emails and can decide visits; admins also get the new-booking SMS and manage users. Users are deactivated, never deleted.
- **Decision**: a user settling a pending booking as **confirmed** or **rejected** (a rejection needs a reason that only staff see). The first decision wins; later changes between confirmed and rejected happen in the admin panel and are kept in the booking's decision history.
- **Decision link**: a user's personal Accept or Reject link in their new-booking email. Its **action** (accept or reject) picks which decision the page offers; opening it changes nothing until the user submits the form. It works without signing in until 23:59 on the visit date, and stops working if the user is deactivated or changes email.
- **Feedback**: the optional post-visit response a visitor gives through a private link, sent the morning after the visit.
