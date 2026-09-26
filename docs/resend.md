# Resend booking notifications

The PHP application queues a visitor confirmation when the optional form email address is supplied, and always queues a notification to the booked destination's contacts in `config/contacts.php`. Bookings without an email address remain valid; no visitor confirmation or feedback invitation is queued for them. Visitor confirmations include the destination team's contact details. Day and Galana overnight details are included, with the booking reference and visitor phone number. The queue and booking are saved in the same SQLite transaction. Existing bookings are not retroactively emailed. The migration preserves old bookings with a null email and retains existing queued messages, IDs, payloads and delivery states.

## Server setup

1. Verify a sending domain in Resend and create an API key with sending permission.
2. Set these environment variables in the PHP hosting service and the worker's environment:

   ```text
   BOOKING_EMAIL_ENABLED=1
   RESEND_API_KEY=<server-side secret>
   RESEND_FROM=bookings@your-verified-domain.example
   BOOKING_DB=/absolute/private/path/bookings.sqlite
   ```

   `RESEND_FROM` must be a plain email address on your verified domain. Do not put credentials in JavaScript, GitHub Pages, or tracked files. `.env` files are ignored but are not automatically loaded; configure the environment through your host.
3. Install PHP cURL alongside PDO SQLite, mbstring and sessions. Run `php bin/migrate.php` using the same database configuration as the web service.
4. Run `php bin/send-booking-emails.php` every minute using the host's scheduler, under the same account and environment as the application. Use the absolute path to the script. Each run processes up to 25 due messages. A functioning scheduler is required for delivery; web requests only enqueue.

Without `BOOKING_EMAIL_ENABLED=1`, bookings work normally and no notification is queued. Disabling the worker stops sending pending messages. To disable all email activity, disable both the web service and worker setting. Configure and test the sender before enabling production notifications. No real email is sent by the test suite.

## Reliability and operations

The worker sends `POST https://api.resend.com/emails` using a bearer API key and a stable `Idempotency-Key`. It stores the original request payload and sender before attempting delivery. A 60-second database lease prevents concurrent workers from processing the same message; network calls time out after 15 seconds. A crash after provider acceptance can safely retry with the same key and payload during the retry window.

Failures retry with exponential backoff capped at one hour. After 23 hours from the first attempt, a message moves to `review`, because Resend retains idempotency keys for only 24 hours. Inspect the Resend dashboard before manually resolving or resending a `review` item. Do not blindly reset it: an earlier timed-out request may have been accepted. Permanent errors also retry within this bounded window; fix sender, credentials or recipients and monitor the queue.

The private `booking_emails` table stores status, attempt count, request payload, provider email ID and a generic failure category. `accepted` means Resend accepted the request, not that the recipient received it. Delivery/bounce webhooks are not implemented; use the Resend dashboard for delivery monitoring. Check `status = 'review'` and overdue `pending` rows in operational monitoring. The worker prints aggregate counts and exits nonzero if a run encounters retries or moves messages to review. No API key or provider response is logged.

Email outages do not undo confirmed bookings. Queue storage failures roll back the booking transaction and return the existing retryable booking error, so a confirmed booking cannot silently lose its required notification.

## Versions and static preview

The integration is shared across all three PHP versions. The design-preview branch retains the backend source, but its export contains no PHP, queue, API credentials or mail-sending code. GitHub Pages cannot run this integration; production needs a PHP host with persistent private SQLite storage and a scheduled worker.

## Checks

Run `php tests/email.test.php` for isolated queue and retry tests, and `node tests/contacts.test.mjs` for real booking/confirmation tests. Tests use temporary databases and mock delivery.

API references: [Send email](https://resend.com/docs/api-reference/emails/send-email) and [idempotency keys](https://resend.com/docs/dashboard/emails/idempotency-keys).

## Email presentation

New visitor confirmations, destination notifications, and feedback invitations use the shared Nyumba email layout: forest green header, cream background, warm typography, clear booking details, and contact/action links. Booking copy adapts to day/overnight and transport/no-transport scenarios; manager messages retain total attendees and visitor contact details. Both HTML and plain-text alternatives are generated. No external fonts, images, scripts, or tracking pixels are required.

Run `php bin/render-email-previews.php` to generate sample HTML/text under `test-results/emails/`, including an index. This does not access the database or send mail. Run `php tests/email-design.test.php` for content, escaping, link, and recipient checks. Browser previews have been checked at mobile and desktop widths; actual mailbox rendering can vary.

Previously queued messages keep their saved payloads and retry keys. The redesign applies to newly queued emails; it does not resend or rewrite existing messages.
