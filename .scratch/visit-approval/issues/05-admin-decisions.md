# 05 — Decide and change decisions in the admin panel

Type: feature
Blocked by: 02, 04
Status: ready-for-agent

**What to build:** Status column and filter, and decide/change with history on the booking detail page, per the spec's [Deciding and changing in /admin](../spec.md#deciding-and-changing-in-admin) and [wording.md](../wording.md) §10.

- [ ] Booking list: Status column; Status filter (All / Awaiting confirmation / Confirmed / Rejected), combined with existing filters and pagination.
- [ ] Detail page: decision panel per status; Confirm/Reject when pending; Change to rejected/confirmed when decided; reason form for reject; inline confirmation prompt; disabled after visit day; saved and conflict messages.
- [ ] All actions call ticket 04's decision service as the signed-in user (`first` mode when pending, `change` otherwise). CSRF on every POST.
- [ ] History list, newest first, including the "Confirmed before visit approval was introduced." line for migrated bookings.
- [ ] Works without JavaScript.
- [ ] Tests in the admin browser suite: column, filter, confirm, reject, change both ways, reason rules, cutoff, conflict, history, and that messages are queued.
