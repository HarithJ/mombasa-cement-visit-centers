# Administration

The PHP deployment provides `/admin` with login-only, read-only access to bookings and feedback. All destinations and public designs in a deployment share its configured database. Separate deployments do not aggregate records. GitHub Pages remains a static visitor-design preview and has no admin interface.

## Private configuration

Set `ADMIN_USERNAME` and `ADMIN_PASSWORD_HASH` in the PHP process environment using the deployment's private secret configuration. There are no default credentials and no registration or password-reset page. Generate a strong unique password in your password manager. Generate its hash on a trusted operator machine with PHP `password_hash(..., PASSWORD_DEFAULT)`; supply the password through a hidden input or stdin, never a shell argument, committed file, or shell history. Only the resulting hash belongs in the server environment.

One way to generate the hash without placing the password in shell history is:

```sh
read -r -s 'admin_password?Admin password: '
printf '%s' "$admin_password" | php -r 'echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT), PHP_EOL;'
unset admin_password
```

This example uses zsh. Treat the resulting hash as a secret as well. To rotate credentials, replace the configured username/hash and reload the PHP service. Existing admin sessions lose access on their next request. Missing or invalid credentials disable administration rather than permitting access.

Use HTTPS in production. If TLS terminates at a reverse proxy, set `ADMIN_TRUSTED_PROXIES` to a comma-separated list of its exact IP addresses. Only those peers may supply `X-Forwarded-Proto: https`. The proxy must overwrite that header. Client address forwarding is deliberately not used: login throttling uses the direct peer address, so visitors behind the same proxy share a throttle bucket. Never configure a broad or untrusted proxy range.

Admin sessions use their own cookie, scoped to `/admin`. Sessions expire after 30 minutes idle or eight hours total. Logout requires a valid form POST. `BOOKING_SESSION_PATH`, when configured, must be private, writable, and persistent across PHP requests. Preserve the existing private `BOOKING_DB` storage configuration.

Five failed logins per direct peer address in a 15-minute window block further attempts until that window expires. Counters persist in private SQLite storage across browser-cookie resets; old counters are removed on subsequent login attempts. Do not remove the database to clear a throttle. Wait for its window to expire. Credential changes do not bypass throttling.

## What staff can see

The list shows 25 bookings per page, newest scheduled visit date first. Search reference/name, filter destination and inclusive visit dates, and open details. For overnight bookings the visit-date filter means arrival, not every day of the stay. Detail pages show contact information, overnight dates and guest count, and submitted feedback. Missing historical emails are labelled explicitly. Feedback attendance is a visitor's answer, not staff check-in.

Times display in Kenya local time. No booking editing, cancellation, check-in, data export, email resend, or feedback moderation is included. Browsing never triggers emails. Private pages are not cacheable and do not include external assets or private invitation links.

## Verification

Run `npm run test:admin` using the same Playwright and browser configuration as the existing integration tests. Tests start an isolated PHP server and database, use fictional visitors, and exercise login, filtering, details, feedback, logout, CSRF, rate limiting, session expiry, and rotation. A temporary test router supplies a controlled clock; there is no public clock-control endpoint or production test-mode environment switch. No real emails are sent.
