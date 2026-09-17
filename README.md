# Visit Nyumba

PHP-rendered visitor booking website for Sahajanand School, Galana and the Feeding Centre. All three UI versions are available together on `main`; day bookings use real SQLite persistence. Galana overnight requests include saved arrival/departure dates and staying guest counts. Hosting is not selected.

## Run locally

Requires PHP with PDO SQLite, mbstring and sessions. No Composer dependencies.

```sh
php bin/migrate.php
php -S 127.0.0.1:8080 -t public
```

Open http://127.0.0.1:8080. Serve only `public/`, never the repository root. Migrations are repeatable and preserve existing records; they also run on first storage access.

## UI versions

- `/v1` — original design, imported from branch `v1` (`9021b48`).
- `/v2` — travel-journal design, imported from branch `v2` (`67f45d7`).
- `/v3` — outdoor-explorer design, imported from branch `v3` (`a8d4f81`).
- `/` and `/index.php` retain the original v1 design.

Each version also accepts a trailing slash or `/index.php`. Booking links, form actions, confirmation redirects and close links stay in the selected version. All versions share the existing booking database, schedules, session and photograph library; they do not create separate booking systems.

`public/index.php` explicitly selects templates in `src/views/`. Version-specific JavaScript and styles are in `public/assets/versions/`; unchanged base CSS, fonts, logos and photos remain shared. The original branches are unchanged.

The PHP development server supports these routes with the command above. When configuring production hosting, route non-file requests to `public/index.php` and keep `public/` as the document root; the application returns 404 for unknown routes. For example, use `try_files $uri $uri/ /index.php?$query_string;` with an appropriately configured Nginx PHP handler. Hosting is not configured by this change.

Run `npm run test:versions` for the version-routing regression suite. It uses isolated temporary SQLite/session storage and checks all routes, assets, galleries, responsive layouts, same-version confirmations, refresh, no-JavaScript submissions, shared persistence and unknown/private paths.

## Private configuration and storage

- Default database: `var/bookings.sqlite`, outside the public document root. `BOOKING_DB` overrides it with a private absolute path.
- `BOOKING_SESSION_PATH` optionally selects a private PHP session directory. Otherwise PHP's configured session storage is used.
- `config/destinations.php` contains centralized schedules. `BOOKING_SCHEDULE` optionally selects another private PHP config file with the same destination/slot structure.
- Times and visit-date validation use Africa/Nairobi; creation timestamps use UTC. Feeding Centre includes 11 am; other slots are provisional and require operational review before launch.
- Production requires HTTPS, persistent writable private storage, restricted permissions and appropriate backups. Do not expose database files, sessions, logs or backups through the web server.

## Booking behavior

Visits collect name, phone, destination, date/time and positive integer attendee count including the contact. Galana can include an overnight request: arrival matches the visit date, departure is later, and staying guests must be whole-number 1–attendees. Unchecked/non-Galana requests discard all overnight fields. No ID, email, accounts, approval workflow, admin screens or outbound messages are introduced. Successful transactional writes are followed by an on-screen registered-visit summary, hard-to-guess reference and masked phone. Registration does not imply a capacity check or accommodation allocation. CSRF/session submission tokens and redirect-after-success prevent accidental replay duplicates. Errors preserve correctable input; failed writes do not display confirmation. Day and overnight booking/confirmation work without JavaScript. Overnight requests do not allocate rooms or guarantee accommodation.

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

An expandable Google Map sits directly above Book my visit. No map iframe is created until it is expanded; changing the destination updates the pin and closing the popup resets the map. Directions links remain available without JavaScript. `config/locations.php` stores the owner-supplied share links, and `config/map-embeds.php` stores exact coordinates resolved from them. The supplied Feeding Center link points to Kibarani Recreation Park.
