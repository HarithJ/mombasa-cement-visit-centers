# 01 — One-night overnight requests

Type: feature
Blocked by: None — can start immediately.
Status: ready-for-agent

**What to build:** The whole of [the spec](../spec.md) as one vertical slice. Ticking "Stay overnight at Galana Farm" is the entire overnight request: the form shows no hint, dates or guests field. The server stores one night with the whole group staying, and every place that shows an overnight request shows a single "requested" line.

- [ ] The overnight section of the booking form is only the checkbox, with no hint text and no arrival, departure or staying-guests fields. This works with and without JavaScript.
- [ ] Galana Farm bookings with the checkbox ticked store arrival = visit date, departure = visit date + 1 day and staying guests = attendees. Submitted date or guest values are not used. Unticked and non-Galana bookings store no overnight values.
- [ ] The on-screen confirmation, visitor and team/manager emails, visitor and manager SMS, and admin booking details show one overnight line ("Overnight stay: Requested" or equivalent wording) with no dates or staying-guest count. Messages still say accommodation is subject to availability. Day bookings show nothing about overnight.
- [ ] Feedback invitations for overnight bookings are still due the morning after the stored departure date.
- [ ] Existing stored bookings are not migrated or altered.
- [ ] Tests follow the spec's Testing Decisions: the browser seam for the form, storage, confirmation, admin and feedback timing, and the existing PHP message tests for email and SMS wording. Multi-night and departure-date checks are removed. The full suite (`npm test`) passes.
