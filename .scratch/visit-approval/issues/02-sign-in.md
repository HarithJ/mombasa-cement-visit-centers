# 02 — Sign-in with accounts, login links and password reset

Type: feature
Blocked by: 01
Status: resolved

**What to build:** Replace the env-var admin login with account sign-in, per the spec's [Sign-in](../spec.md#sign-in) section and [wording.md](../wording.md) §11–12 (sign-in link and reset emails).

- [x] Migration `020-auth-tokens.sql` for single-use, hashed, expiring tokens (purpose: `login`, `reset`, `welcome`) tied to a user and their credential counter; plus whatever per-email request counting the login-link limit needs.
- [x] `/admin/login` signs in by email + password against `users` (active only). `ADMIN_USERNAME`/`ADMIN_PASSWORD_HASH` are removed from code, tests and `docs/admin.md`.
- [x] "Email me a sign-in link": single use, 15 minutes, active accounts only, non-revealing response, max 3 per email per 15 minutes. Link signs the user in.
- [x] "Forgot password?": single-use reset link, 1 hour, non-revealing; set-password page (≥12 chars, confirm field); saving ends that user's other sessions and signs them in.
- [x] Expired/used link page.
- [x] The admin session records the user id and credential counter; every request rejects the session if the user is inactive or the counter changed.
- [x] Existing protections kept: 5 failures per peer per 15 min, 30-min idle, 8-hour absolute, `/admin` cookie, CSRF.
- [x] Emails go through the existing email queue.
- [x] A shared token helper usable by ticket 06's welcome email (`welcome` purpose, 48 hours, signs in once).
- [x] Tests: the admin browser suite is updated to sign in with a seeded user; add coverage for every bullet above.

## Answer

Implemented in `7df2a23`: account sign-in, emailed sign-in links, forgot/reset password, `AuthTokens`, `account_emails` queue (migration 020). Review fix `2d2d44b`: sign-in/welcome links only sign in on a POST button press (scanner-safe), and password reset ends sessions via `session_version` (migration 022) without killing Accept/Reject links.
