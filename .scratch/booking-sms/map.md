# Booking SMS

[Spec](spec.md)

- [01 — Visitor and manager notifications](issues/01-booking-notifications.md): resolved. Approved messages, shared normalization, atomic outbox, scheduled worker, configuration pause, bounded retries and uncertain-send review implemented.

Validation: PHP syntax checks, SMS client and booking/worker suites, and full `npm test` passed. Tests also cover an existing-database upgrade, disabled/no-backfill behavior, separate-connection concurrency and post-send storage failure recovery. No live or sandbox messages sent.
