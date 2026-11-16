# Visit Nyumba

PHP-rendered visitor booking website for Galana Farm, the only destination it offers (see [ADR 0001](docs/adr/0001-galana-farm-only.md)). The UI uses a bold outdoor-explorer identity: panoramic photography, oversized typography, deep green and tangerine, a featured Galana Farm story and a trip-planning form. Day bookings use real SQLite persistence. Galana visits can include a one-night overnight request made with a single checkbox. Hosting is not selected.

## Run locally

Requires PHP with PDO SQLite, mbstring and sessions. No Composer dependencies.

```sh
php bin/migrate.php
php -S 127.0.0.1:8080 -t public
```

Open http://127.0.0.1:8080. Serve only `public/`, never the repository root. Migrations are repeatable and preserve existing records; they also run on first storage access.

## Staff accounts

Staff accounts live in the `users` table (migration `019-users.sql`). Manage them on the server with `php bin/users.php list|add|edit|deactivate|reactivate`; run it with no arguments for usage. Users are never deleted, only deactivated, and the last active admin can't be deactivated or changed to team. New accounts get a random password that is never shown; `add --no-welcome` creates an account without emailing the person.

## Private configuration and storage

- Default database: `var/bookings.sqlite`, outside the public document root. `BOOKING_DB` overrides it with a private absolute path.
- `BOOKING_SESSION_PATH` optionally selects a private PHP session directory. Otherwise PHP's configured session storage is used.
- `config/destinations.php` contains the Galana Farm schedule. `BOOKING_SCHEDULE` optionally selects another private PHP config file with the same structure.
- Times and visit-date validation use Africa/Nairobi; creation timestamps use UTC. Galana Farm slots are provisional and require operational review before launch.
- Production requires HTTPS, persistent writable private storage, restricted permissions and appropriate backups. Do not expose database files, sessions, logs or backups through the web server.

## Booking behavior

Visits collect name, email address, phone, date/time and positive integer attendee count including the contact. A visit can include an overnight request: a single “Stay overnight at Galana Farm” checkbox. A stay is always one night for the whole group, so the server derives arrival (the visit date), departure (the next day) and staying guests (the attendee count) and ignores any submitted values. Confirmations, emails, SMS, admin details and feedback show only that an overnight stay was requested, never stay dates or a staying-guest count. Unchecked requests discard all overnight fields; bookings for any place other than Galana Farm are refused. A valid visitor email address is required. Optional Resend emails send a visitor confirmation and notify the Galana Farm team; see [Resend setup](docs/resend.md). No ID, accounts, approval workflow or admin screens are introduced. Successful transactional writes are followed by an on-screen registered-visit summary, hard-to-guess reference and masked phone. Registration does not imply a capacity check or accommodation allocation. CSRF/session submission tokens and redirect-after-success prevent accidental replay duplicates. Errors preserve correctable input; failed writes do not display confirmation. Day and overnight booking/confirmation work without JavaScript. Overnight requests do not allocate rooms or guarantee accommodation.

Phone policy: 7–15 digits, optional leading +, spaces, parentheses and hyphens. Ten-digit Kenyan 01/07 numbers normalize to +254; twelve-digit 254 numbers receive +. Other numbers retain supplied +, or digits only without guessing their country.

## Content and interaction

Supplied photos are optimized copies, with unchanged originals: fifteen Galana Farm images. Photographs cycle every five seconds, with a pause/resume control and progress rings around the gallery buttons. Destination photographs have no numbered badges. Opening a photo launches a gallery with thumbnails, previous/next buttons and arrow-key navigation. Reduced motion disables automatic cycling. Manrope uses the bundled SIL Open Font License across headings and interface text. Final contact details and operational copy remain owner handoff items; official Nyumba links are used rather than invented contact details.

## Development-only browser tests

```sh
npm install
npx playwright install chromium
npm run test:bookings
```

`npm run test:overnight` runs the Galana suite; `npm run test:ui` runs both day and overnight suites. Tests start an isolated PHP server with temporary SQLite/session storage and remove their own generated data afterward. Database inspection is used only for the agreed persistence assertions, not through a public retrieval endpoint. Screenshots are written to ignored `test-results/`. `UI_BROWSER_PATH` can select an existing Chromium executable. `PLAYWRIGHT_MODULE_PATH` can select an installed Playwright module.

Tickets 02 and 03 are complete; ten day and nine overnight browser groups pass. Requirement evidence is in `docs/day-booking-review.md` and `docs/overnight-booking-review.md`. This is not production deployment approval; broader release hardening remains ticket 04.

## Retired destinations

Sahajanand Special School and Kibarani Feeding center are no longer offered, and the interest-registration form that served them has been removed. Their earlier bookings and interest registrations stay in the database: admin lists those bookings as “Retired destination”, and migration `018-galana-only.sql` parks any of their messages still waiting to send. See [ADR 0001](docs/adr/0001-galana-farm-only.md).

## Booking location maps

An expandable illustrated route sits directly above Book my visit. It uses owner-approved static artwork, with descriptive alternative text and a link to open the full image. The schematic illustrations are not to scale. Google Maps directions remain available through the existing external link; no map iframe or third-party map request is loaded. The illustrations also work without JavaScript. `config/route-illustrations.php` maps Galana Farm to its local asset, and `config/locations.php` retains the owner-supplied directions link.

## Booking emails

Resend destination notifications are available in the PHP backend. See [configuration, worker setup and delivery monitoring](docs/resend.md). Sending is disabled by default and is not available in the GitHub Pages preview.

## Post-visit feedback

Optional next-morning feedback invitations and private forms are available for day and Galana overnight bookings, regardless of attendance. See [feedback configuration and operations](docs/feedback.md). Sending is disabled by default.

Visitors can optionally request company-arranged transport with the unchecked booking checkbox. The saved choice appears in confirmations, admin details, and booking emails. Transport is subject to availability and requires team confirmation; this does not trigger SMS. Existing bookings default to transport not requested.

Optional Africa’s Talking booking SMS confirms visits to the visitor and alerts destination managers through a durable queue and scheduled worker. Sending is disabled by default. See [configuration, recovery, and mocked tests](docs/africas-talking.md).

### Booking notification routing

Booking emails go to the Galana Farm contacts in `config/contacts.php`, plus `harithjaved@gmail.com`
and `apoorvchit@gmail.com`. Booking SMS alerts go to the
Galana Farm contact numbers plus `+254792488382` and `+254115933896`. Visitor confirmations and feedback emails are unchanged.
The existing email/SMS enablement flags still apply.
