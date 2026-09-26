# Booking SMS through Africa’s Talking

When `BOOKING_SMS_ENABLED=1`, a new booking queues a conversational confirmation to its visitor and a notification to every configured contact for its destination. The four day/overnight and transport/no-transport scenarios have complete templates in `BookingSms`. Visitor messages address the visitor as “you”; manager messages always include the total attendee count (including the contact), visitor phone, and applicable overnight guest count. All dates/times use Africa/Nairobi. Transport and accommodation are requests subject to availability.

The existing destination contact configuration supplies manager numbers. Numbers are normalized using the same helper as booking validation. Duplicate normalized manager numbers are collapsed. Ambiguous numbers do not block a valid booking; their SMS is held in `review`. A visitor who is also a configured manager receives the two different audience messages.

## Server setup

Configure the web service and scheduled worker through the host’s private environment:

| Variable | Purpose |
| --- | --- |
| `BOOKING_SMS_ENABLED` | Set to `1` to queue and dispatch new booking messages; disabled by default |
| `AFRICAS_TALKING_USERNAME` | Account username; exactly `sandbox` in sandbox mode |
| `AFRICAS_TALKING_API_KEY` | Secret API key for that account/environment |
| `AFRICAS_TALKING_ENVIRONMENT` | Explicit `sandbox` or `live` |
| `AFRICAS_TALKING_SENDER_ID` | Optional approved sender; omitted if empty |
| `BOOKING_DB` | Same private SQLite database used by the web application |

PHP cURL and PDO SQLite are required. `.env` files are not automatically loaded. Do not commit credentials or expose them in public assets. Production requires the appropriate Africa’s Talking account, balance, and sender provisioning.

Run `php bin/migrate.php`, then schedule `php bin/send-booking-sms.php` every minute using absolute paths and the web service’s database permissions/environment. Each run processes at most 25 messages. The web request only queues; it never calls the provider. Enable production only when the intended environment, credentials, sender, and scheduler are configured. This implementation’s verification sends no real or sandbox SMS.

Disable the flag in both the web service and worker to stop new jobs and sending. Pending jobs remain stored. Review their age before resuming. Historical bookings and submission replays are never backfilled. Email flags and email sending remain independent. Static previews cannot send SMS.

## Reliability and operations

Bookings and their SMS outbox records commit together. Queue storage failure rolls back the booking; provider outages after commit cannot undo it. Recipient, audience, and message text are snapshotted and protected by a unique booking/audience/recipient constraint.

The worker validates local configuration before claiming work: missing credentials, invalid environment, or unavailable cURL cause a nonzero exit without changing jobs or attempts. HTTP authentication/authorization errors and provider account/sender/balance problems persistently pause the worker through `sms_control`, return the current job to pending, and do not consume attempts. Initial failed configuration does not freeze the sender profile. Fix the account/configuration, stop concurrent workers, then clear the pause in the private database:

```sql
UPDATE sms_control SET paused = 0, reason = NULL WHERE id = 1;
```

This resumes pending work; it does not reset accepted or review jobs. Investigate the provider dashboard before manually changing any `review` or `sending` record. Never blindly reset those jobs.

Claims are committed before HTTP dispatch; network activity runs outside the database lock with a 15-second timeout and a 60-second lease. Expired sending claims become `review`, because a crashed worker may already have sent the SMS. Claim tokens prevent stale workers from overwriting recovered state. Sending environment, username, and sender are frozen at the first attempt; a change on a retry requires review. API keys are never stored in the queue and may be rotated.

Only connection failures known to precede submission and HTTP rate-limit rejections retry automatically, with exponential backoff starting at 60 seconds, capped at one hour, and limited to five attempts or 24 hours from the first attempt. Timeouts, ambiguous HTTP/server responses, and malformed success responses require review. Definitive recipient rejection also requires review. There is no provider idempotency assumption and no exactly-once delivery guarantee.

Accepted means Africa’s Talking accepted the message (status codes 100, 101, or 102 with a matching recipient and message ID), not handset delivery. Delivery webhooks are not included. The worker prints aggregate counts and returns nonzero for retries, review transitions, or a pause. Monitor pending backlog, `review` jobs, and `sms_control`; previously reviewed jobs do not repeatedly change the worker exit status. Error output and queue diagnostics contain generic categories, not credentials or raw provider responses.

Natural message wording and booking references can require multiple SMS segments; non-GSM characters in names can increase segment counts. No content is silently truncated.

## Verification

`npm run test:sms` runs the client and booking/worker tests with fake HTTP transport and temporary databases. The full suite includes them. Tests exercise all scenarios, normalization, routing, atomic persistence, replay prevention, disabled mode, configuration pauses, retries, concurrent claims, and crash recovery. No sending calls are made to Africa’s Talking during tests.

Provider contract references: [official SMS client](https://github.com/AfricasTalkingLtd/africastalking-php/blob/master/src/SMS.php), [configuration](https://github.com/AfricasTalkingLtd/africastalking-php/blob/master/src/AfricasTalking.php), and [response status codes](https://help.africastalking.com/en/articles/16150386-messaging-error-codes).
