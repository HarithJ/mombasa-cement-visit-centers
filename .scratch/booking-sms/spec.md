# Booking SMS notifications

Status: ready-for-agent

Send the approved complete conversational messages to the booking contact and every configured manager for the booked destination. Select among day/overnight and transport/no-transport scenarios. Visitors are addressed as “you”; managers receive the total attendee count, visitor phone, and applicable overnight guest count. Requests for transport and accommodation remain subject to confirmation. Use correct singular/plural wording and Africa/Nairobi date/time formatting.

Reuse the booking phone normalization policy. Queue immutable recipient/message snapshots in the booking transaction, only for newly created bookings, with audience/recipient uniqueness. Send through the Africa’s Talking client in a scheduled worker, independently of email. No historical backfill or duplicate dispatch on submission replay.

Validate configuration before claiming work. Configuration problems do not consume attempts; provider account/authentication errors pause sending. Use bounded retries only for definite transient failures; uncertain sends and expired claims require review. Record provider acceptance without claiming handset delivery. Tests use fake transport and temporary databases; default configuration remains disabled.

This supersedes the integration-only scope for the new notification feature; the earlier client spec remains historical.
