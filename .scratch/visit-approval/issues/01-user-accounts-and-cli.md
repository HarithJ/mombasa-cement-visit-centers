# 01 — User accounts table and `bin/users.php`

Type: feature
Blocked by: None — can start immediately.
Status: ready-for-agent

**What to build:** The `users` table and the server command, per the spec's [Users](../spec.md#users), [Server command](../spec.md#server-command) and [Initial accounts](../spec.md#initial-accounts) sections. No web UI in this ticket.

- [ ] Migration `019-users.sql` creates `users` (name, email unique case-insensitive, phone, role `admin`|`team`, active, password_hash, created/updated timestamps, plus a per-user `session_version`/credential counter that later tickets bump to invalidate sessions and links).
- [ ] A small `Users` module (e.g. `src/UserStore.php`) owns create/edit/deactivate/reactivate/list/find-by-email and enforces: valid name/email/phone, unique email, last-active-admin cannot be deactivated or downgraded, an actor cannot deactivate themselves (CLI has no actor; only the last-admin rule applies there). Deactivation and email change bump the user's credential counter.
- [ ] New users get a strong random placeholder password; only its hash is stored, never printed.
- [ ] `php bin/users.php list|add|edit|deactivate|reactivate` mirrors the module. `add` accepts `--no-welcome`; without it, it queues the welcome email if ticket 06's sender exists — until then, `add` without `--no-welcome` may simply queue nothing and say so. (Ticket 06 wires the welcome email.)
- [ ] Active-user queries that later tickets need: all active users; all active admins.
- [ ] Tests (PHP, isolated temp DB): add/edit/deactivate/reactivate, validation errors from wording §13, uniqueness, last-admin guard, counter bump, CLI parity and that no password appears in CLI output.
