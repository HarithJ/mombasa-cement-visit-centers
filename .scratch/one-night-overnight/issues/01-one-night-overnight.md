# 01 — One-night overnight requests

Type: feature
Blocked by: None — can start immediately.
Status: resolved

**What to build:** The whole of [the spec](../spec.md) as one vertical slice. Ticking "Stay overnight at Galana Farm" is the entire overnight request: the form shows no hint, dates or guests field. The server stores one night with the whole group staying, and every place that shows an overnight request shows a single "requested" line.

- [x] The overnight section of the booking form is only the checkbox, with no hint text and no arrival, departure or staying-guests fields. This works with and without JavaScript.
- [x] Galana Farm bookings with the checkbox ticked store arrival = visit date, departure = visit date + 1 day and staying guests = attendees. Submitted date or guest values are not used. Unticked and non-Galana bookings store no overnight values.
- [x] The on-screen confirmation, visitor and team/manager emails, visitor and manager SMS, and admin booking details show one overnight line ("Overnight stay: Requested" or equivalent wording) with no dates or staying-guest count. Messages still say accommodation is subject to availability. Day bookings show nothing about overnight.
- [x] Feedback invitations for overnight bookings are still due the morning after the stored departure date.
- [x] Existing stored bookings are not migrated or altered.
- [x] Tests follow the spec's Testing Decisions: the browser seam for the form, storage, confirmation, admin and feedback timing, and the existing PHP message tests for email and SMS wording. Multi-night and departure-date checks are removed. The full suite (`npm test`) passes.

## Answer

Implemented on branch `one-night-overnight` in `6ad47aa` (one-night checkbox, server-derived arrival/departure/guests, single "Overnight stay: Requested" line in confirmation, emails, SMS and admin) and `c7dada3` (code-review fixes: feedback email and page show the visit date with "Overnight stay" instead of a date range; derived values only for a valid visit date; non-Galana tamper test; README and privacy wording). Full suite passes (55 checks). Not pushed.
