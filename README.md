# Visit Nyumba

PHP-rendered visitor booking website for Sahajanand School, Galana and the Feeding Centre. The owner-approved UI is preserved; day bookings now use real SQLite persistence. Galana overnight requests include saved arrival/departure dates and staying guest counts. Hosting is not selected.

## Run locally

Requires PHP with PDO SQLite, mbstring and sessions. No Composer dependencies.

```sh
php bin/migrate.php
php -S 127.0.0.1:8080 -t public
```

Open http://127.0.0.1:8080. Serve only `public/`, never the repository root. Migrations are repeatable and preserve existing records; they also run on first storage access.

## Private configuration and storage

- Default database: `var/bookings.sqlite`, outside the public document root. `BOOKING_DB` overrides it with a private absolute path.
- `BOOKING_SESSION_PATH` optionally selects a private PHP session directory. Otherwise PHP's configured session storage is used.
- `config/destinations.php` contains centralized schedules. `BOOKING_SCHEDULE` optionally selects another private PHP config file with the same destination/slot structure.
- Times and visit-date validation use Africa/Nairobi; creation timestamps use UTC. Feeding Centre includes 11 am; other slots are provisional and require operational review before launch.
- Production requires HTTPS, persistent writable private storage, restricted permissions and appropriate backups. Do not expose database files, sessions, logs or backups through the web server.

## Booking behavior

Visits collect name, email address, phone, destination, date/time and positive integer attendee count including the contact. Galana can include an overnight request: arrival matches the visit date, departure is later, and staying guests must be whole-number 1–attendees. Unchecked/non-Galana requests discard all overnight fields. A valid visitor email address is required. Optional Resend emails send a visitor confirmation and notify destination contacts; see [Resend setup](docs/resend.md). No ID, accounts, approval workflow or admin screens are introduced. Successful transactional writes are followed by an on-screen registered-visit summary, hard-to-guess reference and masked phone. Registration does not imply a capacity check or accommodation allocation. CSRF/session submission tokens and redirect-after-success prevent accidental replay duplicates. Errors preserve correctable input; failed writes do not display confirmation. Day and overnight booking/confirmation work without JavaScript. Overnight requests do not allocate rooms or guarantee accommodation.

Phone policy: 7–15 digits, optional leading +, spaces, parentheses and hyphens. Ten-digit Kenyan 01/07 numbers normalize to +254; twelve-digit 254 numbers receive +. Other numbers retain supplied +, or digits only without guessing their country.

## Content and interaction

Three official Nyumba logos are distributed across the header without navigation links. Supplied photos are optimized copies, with unchanged originals: seven school, fourteen Feeding Centre and fourteen Galana images. Galleries cycle every 2.5 seconds while hovered and stop on leave. A progress ring surrounds the gallery button; destination photographs have no numbered badges; touch/keyboard users have a next-photo control. Reduced motion disables automatic cycling. Manrope uses the bundled SIL Open Font License. Final contact details and operational copy remain owner handoff items; official Nyumba links are used rather than invented contact details.

## Development-only browser tests

```sh
npm install
npx playwright install chromium
npm run test:bookings
```

`npm run test:overnight` runs the Galana suite; `npm run test:ui` runs both day and overnight suites. Tests start an isolated PHP server with temporary SQLite/session storage and remove their own generated data afterward. Database inspection is used only for the agreed persistence assertions, not through a public retrieval endpoint. Screenshots are written to ignored `test-results/`. `UI_BROWSER_PATH` can select an existing Chromium executable. `PLAYWRIGHT_MODULE_PATH` can select an installed Playwright module.

Tickets 02 and 03 are complete; ten day and seven overnight browser groups pass. Requirement evidence is in `docs/day-booking-review.md` and `docs/overnight-booking-review.md`. This is not production deployment approval; broader release hardening remains ticket 04.

## Booking location maps

An expandable illustrated route sits directly above Book my visit. Each destination uses its owner-approved static artwork, with descriptive alternative text and a link to open the full image. The schematic illustrations are not to scale. Google Maps directions remain available through the existing external link; no map iframe or third-party map request is loaded. The illustrations also work without JavaScript. `config/route-illustrations.php` maps destinations to local assets, and `config/locations.php` retains the owner-supplied directions links.

## Booking emails

Resend destination notifications are available in the PHP backend. See [configuration, worker setup and delivery monitoring](docs/resend.md). Sending is disabled by default and is not available in the GitHub Pages preview.

## Post-visit feedback

Optional next-morning feedback invitations and private forms are available for day and Galana overnight bookings, regardless of attendance. See [feedback configuration and operations](docs/feedback.md). Sending is disabled by default.
