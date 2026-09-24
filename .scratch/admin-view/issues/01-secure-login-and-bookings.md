# 01 — Secure login and bookings list

**What to build:** An administrator can sign in using privately provisioned credentials, review a paginated list of existing bookings, and sign out. Public visitors cannot access booking records. This is the first usable, read-only admin slice, with no registration.

**Blocked by:** None — can start immediately.

**Status:** resolved

- [x] Provide one shared, server-rendered admin login and bookings list using the existing PHP application and SQLite storage. No registration, account management, or default credentials exist.
- [x] Accept a privately configured username and password hash, verify passwords securely, and document credential generation and rotation. Missing or invalid configuration fails closed without exposing secrets.
- [x] Authorize every protected request before accessing private data. Unauthenticated requests lead to login; any return destination is restricted to validated local admin routes.
- [x] Keep admin authentication separate from public session state, regenerate the session identifier on successful login, and use strict sessions with HttpOnly/SameSite cookies and Secure on HTTPS. Require HTTPS in production while supporting local development.
- [x] Protect login and POST logout against CSRF. Logout invalidates authentication; GET requests cannot log users in or out. Expire authentication after 30 minutes of inactivity or eight hours total, and invalidate it after credential rotation.
- [x] Enforce persistent failed-login throttling across fresh cookies, initially five failures per client address within 15 minutes. Use generic errors, trust forwarding headers only from configured proxies, and bound and expire throttle records.
- [x] Display booking reference, visitor name, destination, visit date/time, and attendee count. Sort by visit date descending and identifier descending, with bounded pages of 25 records and an understandable empty state.
- [x] Display readable Kenya-local dates and times without showing the timezone identifier. Escape visitor content, mark private responses no-store and noindex, and avoid third-party assets that disclose private referrers.
- [x] Login, pagination, and logout work without JavaScript and on mobile, with labelled controls, visible focus, accessible errors, and keyboard navigation.
- [x] Test the real PHP application using isolated database/session storage and privately supplied test credentials. Cover successful and failed login, missing configuration, protected URLs, CSRF, throttling, session fixation, both expiry limits, credential rotation, logout, unsafe return destinations, pagination, and hostile stored content.
- [x] Verify admin reads neither mutate bookings nor send emails; public booking and feedback flows remain functional. Any throttle-storage migration preserves existing bookings, responses, and notification records.
- [x] Keep admin functionality and private data out of static exports from this first slice onward. Do not commit secrets or send real test emails.

## Answer

Implemented and verified through the real PHP browser integration suite with isolated storage. Authentication, filter and detail behavior, feedback states, and relevant security cases pass. Rollout across maintained branches is tracked in ticket 04.
