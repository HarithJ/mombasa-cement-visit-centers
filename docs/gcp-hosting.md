# GCP server

Provisioned on 2026-09-23 using gcloud.

| Setting | Value |
| --- | --- |
| Project | `corrugated-sheets-ltd` |
| VM | `visit-nyumba-web` |
| Zone | `africa-south1-a` (Johannesburg) |
| Machine | `e2-small`, 2 GB RAM, shared CPU |
| OS | Ubuntu 24.04 LTS |
| Disk | 30 GB balanced Persistent Disk |
| Static public IP | `34.35.66.16` (`visit-nyumba-ip`) |
| Network / subnet | `visit-nyumba` / `visit-nyumba-johannesburg` |

The server setup is in `ops/gcp/startup.sh`. It installs Nginx, PHP 8.3 FPM, SQLite, mbstring, curl, Certbot and unattended security upgrades. It configures a 1 GB swap file and a five-worker, on-demand PHP pool. PHP release `2fb0459` is deployed at `/srv/visit-nyumba/releases/2fb0459`, linked as `/srv/visit-nyumba/current`. No email credentials are installed.

## Access

OS Login is enabled. SSH is allowed only from Google's IAP range, not from the public Internet. Use an account with the required OS Login and IAP permissions:

```sh
gcloud compute ssh visit-nyumba-web --project=corrugated-sheets-ltd --zone=africa-south1-a --tunnel-through-iap
```

Only TCP 80/443 are publicly allowed. The VM has no service account. Secure Boot and deletion protection are enabled; deleting the VM does not automatically delete its disk. The project's default network was not modified.

## Storage and backups

- Application directory reserved at `/srv/visit-nyumba`.
- PHP environment points `BOOKING_DB` to `/var/lib/visit-nyumba/bookings.sqlite` and `BOOKING_SESSION_PATH` to `/var/lib/visit-nyumba/sessions`.
- `/usr/local/sbin/visit-nyumba-backup` uses SQLite's online backup command and verifies integrity. It runs daily at 03:15 Africa/Nairobi once a database exists. Local copies older than 14 days are removed.
- `visit-nyumba-daily` snapshots the disk daily starting in the 02:00 UTC hour, retaining snapshots for 14 days. Snapshots are stored in `africa-south1` and preserved under the policy if the source disk is deleted.
- Local database backups are on the same disk; disk snapshots provide the separate recovery copies. Restore testing with actual application data remains a deployment task.

## Domain and deployment

### 2026-10-23 homepage redesign release

Deployed `homepage-redesign` commit `9348c32` as
`/srv/visit-nyumba/releases/homepage-redesign-9348c32` (git archive of `public`,
`src`, `config`, `migrations` and `bin`), replacing `homepage-redesign-3b5496f`,
which remains for rollback. Before switching, the database was backed up to
`/var/backups/visit-nyumba/pre-deploy-9348c32-20261023T084152Z.sqlite`
(integrity `ok`; it holds the one school request deleted below).

Migration `015-drop-school-requests-and-donations.sql` dropped the school
request and M-Pesa tables; there were no donation records. Bookings were
unchanged (9) and `quick_check` returned `ok`. The school-request, donation and
M-Pesa routes now return 404; no cron job or timer referenced their removed
scripts. PHP-FPM was reloaded.

Post-deploy checks: homepage 200 with booking form/CSRF field, all three local
CSS/JavaScript assets 200, `/admin` redirects to login, Nginx and PHP-FPM
active with no error-level journal entries, and the email and SMS workers'
next scheduled runs succeeded. No test bookings were submitted.

### 2026-10-22 kernel reboot

Rebooted production at approximately 08:03 EAT to activate kernel
`7.0.0-1014-gcp` (previously `7.0.0-1011-gcp`). Confirmed a new boot ID,
systemd state `running`, no failed units and no pending reboot marker.
All services running before maintenance returned, including Nginx, PHP-FPM,
SSH, cron, time synchronization, Google agents and unattended upgrades.
The PHP security package remains `8.3.6-0ubuntu0.24.04.13`.

Email and SMS timers resumed; both workers completed their first post-boot
scheduled run at 08:03:50 EAT with exit status 0 and result `success`.
Certbot's timer and cron are active; the backup cron file and executable
remain present. Backup restore and external message delivery were not tested.

Post-boot HTTPS checks passed for the homepage, admin login, school request
and donation pages, all seven local CSS/JavaScript assets referenced by the
homepage, session cookie issuance, booking form/CSRF presence and the
unauthenticated admin redirect. SQLite read-only `quick_check` returned `ok`,
Nginx configuration validation passed, and the current boot's Nginx/PHP-FPM
error-level journals were empty. No test bookings or payments were submitted.

### 2026-10-22 PHP security update

Upgraded the nine installed PHP 8.3 runtime/extension packages from Ubuntu
`8.3.6-0ubuntu0.24.04.12` to `8.3.6-0ubuntu0.24.04.13`. The installed package
changelog explicitly records fixes for CVE-2026-109618 and CVE-2026-109619;
see [Ubuntu USN-8948-1](https://ubuntu.com/security/notices/USN-8948-1).
PHP-FPM was explicitly restarted at 07:34:44 EAT. PHP-FPM and Nginx were active,
the running FPM executable was not marked deleted, and no FPM journal errors
were reported after the restart.

Post-update checks passed: HTTPS homepage, admin login, school request and
donation pages returned 200 with expected content; booking form and CSRF field
were present; a session cookie was issued; unauthenticated `/admin` redirected
to login; three stylesheet requests returned 200. SQLite read-only
`PRAGMA quick_check` returned `ok`, required PHP modules were present, and
`nginx -t` passed. No real bookings, payments or notifications were created;
these were smoke checks, not a full transaction or compromise investigation.
The package manager also reported a pending kernel reboot, which was not
performed as part of this PHP update.

### 2026-09-24 update

Admin hash configuration correction: PHP-FPM interprets a literal leading `$` in a pool `env[]` value as an environment lookup, so a directly pasted bcrypt hash becomes empty in workers. The existing hash is stored root-only in `/etc/visit-nyumba-admin.env`, loaded by `/etc/systemd/system/php8.3-fpm.service.d/visit-nyumba-admin.conf`. The pool references it as `env[ADMIN_PASSWORD_HASH] = $ADMIN_PASSWORD_HASH`. The username stays in the pool. After changing the systemd environment file, restart PHP-FPM (a reload is insufficient to reload systemd environment). Never print the environment or the full FPM configuration dump in diagnostics: both may contain secrets.

Current release: `v3` commit `31409ac`, directory `/srv/visit-nyumba/releases/v3-31409ac`. Includes refined destination booking, route illustrations, and read-only admin views. All six SQL migrations are included; `006-admin-throttle.sql` adds login throttling storage. No extra pre-deployment backup was taken, as explicitly requested by the owner. Existing database and service credentials are preserved. The email timer is enabled (superseding the initial disabled-email notes below).

Admin username/password hash still require private PHP-FPM configuration; no default credentials were introduced. Admin, email, feedback, migration, contacts and overnight test suites passed; the known day-suite header-navigation assertion still fails. Earlier functional day-booking checks passed. Production rollout is checked for route/assets availability and schema integrity.

### V3-only release

Deployment correction: the initial archives omitted `migrations/`. `bin/migrate.php` misleadingly reported success with only `schema_migrations` present; the earlier integrity check did not verify application tables. The missing SQL migrations from `13099a0` were subsequently restored and applied. Every future release archive MUST include `migrations/` alongside `public`, `src`, `config` and CLI scripts. Verify all five migrations and the `bookings`, `booking_emails`, and feedback tables, not only SQLite integrity. The original `2fb0459` rollback directory also lacks migrations and must be repaired before reuse.

The `v3` branch at `13099a0` supersedes the initial deployment. Its core booking, schedule, notification, and feedback files match `design-preview` at `2fb0459`; differences are standalone routing and asset layout. The exact Git archive is deployed at `/srv/visit-nyumba/releases/v3-13099a0` via the `current` symlink. The previous release remains available for rollback. The database is shared and backed up before switching.

Nginx redirects `/v1`, `/v2`, `/v3` (including trailing slash and index.php variants) to `/`, preserving query strings and request methods with 308. Versioned feedback links similarly redirect to `/feedback`.

PHP email, feedback worker and migration tests, and browser overnight, contacts and feedback suites passed. Day booking functional checks passed, but the suite stops at its legacy assertion expecting no header navigation: standalone v3 intentionally includes navigation. Thus the full test runner does not pass; the remaining day-suite checks after that assertion were not executed. Production route and asset checks are performed after activation.

The user updated GoDaddy DNS for `jayram.org` to `34.35.66.16`, verified through Google's DNS-over-HTTPS resolver on 2026-09-23. Local DNS caches temporarily retained the old addresses. Nginx serves the PHP app over HTTPS using `ops/gcp/jayram.org.conf`, with HTTP redirected to HTTPS. The initial Let's Encrypt certificate expires 2026-12-22; Certbot scheduled renewal is enabled. The ACME account was registered without a contact email. No `www` hostname has been requested or configured.

Only `public/` is served. Migrations and the initial SQLite backup passed integrity checks. PHP source lint and local email/feedback worker regression checks passed. Email and feedback invitations remain disabled pending private Resend configuration and worker scheduling. No production test bookings were inserted. Full booking acceptance and backup restore testing remain operational follow-ups. The deployed PHP application saves bookings; the separate static preview build does not.

Billing is active for the VM, disk, public IP and snapshot storage. Budget alerts and application uptime monitoring are not configured by this server setup.
