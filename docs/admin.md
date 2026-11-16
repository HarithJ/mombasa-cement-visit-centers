# Administration

The PHP deployment provides `/admin`, a signed-in workspace for bookings, their decisions and feedback. All destinations and public designs in a deployment share its configured database. Separate deployments do not aggregate records. GitHub Pages remains a static visitor-design preview and has no admin interface.

## Accounts and sign-in

Staff sign in with their own account from the `users` table (see `php bin/users.php`). There are no shared credentials and no default accounts; the old `ADMIN_USERNAME` and `ADMIN_PASSWORD_HASH` settings are no longer read and should be removed from the server environment and PHP-FPM configuration.

Active users can sign in three ways:

- **Email and password.** Passwords are at least 12 characters and are stored only as hashes.
- **Email me a sign-in link.** A single-use link, valid for 15 minutes, at most 3 per email address per 15 minutes.
- **Forgot password?** A single-use reset link, valid for one hour, leads to a set-password page. Saving a password signs the user in and ends their other sessions.

Link requests always show the same message, whether or not the email has an account, and inactive accounts are never sent links. Only a hash of each link token is stored. Deactivating a user, changing their email or resetting their password ends their sessions and stops their unused links. Sign-in and reset emails go through the email queue (`bin/send-booking-emails.php`, once a minute) and are sent whenever the worker runs, independent of `BOOKING_EMAIL_ENABLED`. Links are built from `BOOKING_SITE_URL`, which must be the canonical HTTPS origin (see `docs/feedback.md`); requests never use the incoming Host header.

Use HTTPS in production. If TLS terminates at a reverse proxy, set `ADMIN_TRUSTED_PROXIES` to a comma-separated list of its exact IP addresses. Only those peers may supply `X-Forwarded-Proto: https`. The proxy must overwrite that header. Client address forwarding is deliberately not used: login throttling uses the direct peer address, so visitors behind the same proxy share a throttle bucket. Never configure a broad or untrusted proxy range.

Admin sessions use their own cookie, scoped to `/admin`. Sessions expire after 30 minutes idle or eight hours total. Logout requires a valid form POST. `BOOKING_SESSION_PATH`, when configured, must be private, writable, and persistent across PHP requests. Preserve the existing private `BOOKING_DB` storage configuration.

Five failed password sign-ins per direct peer address in a 15-minute window block further attempts until that window expires. Counters persist in private SQLite storage across browser-cookie resets; old counters are removed on subsequent sign-in attempts. Do not remove the database to clear a throttle. Wait for its window to expire. Password changes do not bypass throttling.

## What staff can see

The list shows 25 bookings per page, newest scheduled visit date first. Search reference/name, filter destination, status (All, Awaiting confirmation, Confirmed, Rejected) and inclusive visit dates, and open details. Filters carry through pagination, the CSV download and the link back from a booking. For overnight bookings the visit-date filter means arrival, not every day of the stay. Detail pages show contact information, overnight dates and guest count, and submitted feedback. Missing historical emails are labelled explicitly. Feedback attendance is a visitor's answer, not staff check-in.

## Deciding visits

Each booking page has a **Decision** panel. Any signed-in user can decide, and the decision is recorded under their account:

- **Awaiting confirmation:** Confirm visit or Reject visit.
- **Confirmed / Rejected:** Change to rejected or Change to confirmed. A booking never goes back to awaiting confirmation.

Every decision has a confirmation step before it is saved, and rejecting needs a reason (at most 500 characters) that only the team sees. Saving sends the same messages as the email Accept/Reject buttons: the visitor gets an email (if they gave one) and an SMS, every other active user gets a team email, and the feedback invitation is scheduled or cancelled. Messages go through the usual queues (`BOOKING_EMAIL_ENABLED`, `BOOKING_SMS_ENABLED`).

If someone else decides the visit while the page is open, nothing is overwritten: the page says so and shows the latest status. Decisions can be made or changed until 23:59 Kenya time on the visit date; after that the panel has no buttons.

The panel's history lists every decision, newest first, with who made it, when and any rejection reason. Bookings made before visit approval was introduced say so instead.

Admin pages run no JavaScript; every form is a plain POST with a CSRF token.

Times display in Kenya local time. No booking editing, cancellation, check-in, email resend, or feedback moderation is included. Browsing never triggers emails. Private pages are not cacheable and do not include external assets or private invitation links.

## Verification

Run `npm run test:admin` using the same Playwright and browser configuration as the existing integration tests. Tests start an isolated PHP server and database, use fictional visitors, and exercise sign-in with seeded fictional users, filtering, details, feedback, logout, CSRF, rate limiting, session expiry, and deactivation; `tests/admin-decisions.test.mjs` covers the status column and filter, confirming, rejecting, changing, reason rules, the visit-day cutoff, conflicts, history and queued messages. `npm run test:sign-in` covers sign-in links, password reset and expired links. A temporary test router supplies a controlled clock; there is no public clock-control endpoint or production test-mode environment switch. No real emails are sent.
