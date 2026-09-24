# Simple admin view

Status: ready-for-agent
Type: feature

## Problem Statement

The website records bookings and post-visit feedback, but staff have no private interface to review them. The owner wants a simple admin view with login only and no registration.

## Solution

Provide one protected admin area for the PHP application. Staff sign in using credentials provisioned by the server operator, review bookings across all three destinations, open booking details and associated feedback, and sign out.

Login without registration is the confirmed product requirement. A read-only bookings list and details view with associated feedback is the proposed minimum useful scope, inferred from the existing application. Editing bookings and operational workflows are deliberately deferred.

## User Stories

1. As an administrator, I want to sign in with an existing username and password, so that I can access private booking information.
2. As an administrator, I want no registration step, so that access remains simple and controlled by the operator.
3. As an operator, I want to provision credentials privately, so that the website cannot create public administrator accounts.
4. As an administrator, I want a clear error for unsuccessful login, so that I know to retry without exposing account information.
5. As an operator, I want repeated failed logins limited, so that automated guessing is constrained.
6. As an administrator, I want to land on the bookings list after login, so that I can immediately review visits.
7. As an administrator, I want bookings from all destinations in one view, so that I do not need separate accounts or dashboards.
8. As an administrator, I want to filter by destination and visit date range, so that I can find relevant visits.
9. As an administrator, I want to search by booking reference or visitor name, so that I can locate a particular booking.
10. As an administrator, I want paginated results with a clear order, so that the interface stays usable as records accumulate.
11. As an administrator, I want to see the reference, visitor, destination, date, time, and group size, so that I can understand a booking at a glance.
12. As an administrator, I want to open a booking's full details, so that I can review the information supplied by its contact.
13. As an administrator, I want to see the visitor's phone and email, so that I can find their contact details when needed.
14. As an administrator, I want older bookings without an email to display clearly, so that missing historical information is not mistaken for an error.
15. As an administrator, I want Galana overnight arrival, departure, and overnight guest count shown explicitly, so that I can distinguish a stay from a day visit.
16. As an administrator, I want submitted feedback alongside its booking, so that I can understand the response in context.
17. As an administrator, I want non-attendance responses distinguished from ratings, so that I do not interpret them as poor reviews.
18. As an administrator, I want an explicit no-feedback-yet state, so that missing feedback is understandable.
19. As an administrator, I want empty results and unavailable records explained clearly, so that I know whether to change a filter or return to the list.
20. As an administrator using a phone or keyboard, I want readable layouts and labelled controls, so that I can review bookings without relying on a mouse or wide screen.
21. As an administrator without JavaScript, I want login, filtering, details, and logout to work, so that core administration remains reliable.
22. As an administrator, I want to sign out and have inactive sessions expire, so that private information is not left accessible on a shared device.
23. As a visitor, I want my booking and feedback inaccessible to unauthenticated users, so that my personal information remains private.
24. As a maintainer, I want the same admin behavior in the combined application and each version branch, so that every maintained PHP deployment supports the feature.

## Implementation Decisions

- **Confirmed:** login only; no registration. **Proposed defaults:** a single operator-provisioned administrator identity, one shared admin design, read-only booking and feedback access, and the filters described here. These are implementation assumptions, not additional owner-confirmed product decisions.
- Add a shared admin request handler, authentication boundary, server-rendered templates, and narrowly scoped booking/feedback read queries. Reuse the existing PHP router, SQLite store, session infrastructure, and escaping conventions. Do not create a separate frontend framework or public data API.
- Use `/admin/login` for login and `/admin` for the protected bookings list, with protected booking detail routes and a POST logout endpoint. In combined deployments, all three public versions share the admin area; standalone branches expose the same admin area at their application root.
- Provision a username and a password hash through private server configuration. Use PHP password hashing and verification facilities; never store a plaintext password or supply default credentials. Provide operator documentation for generating and rotating credentials. No administrator-account schema, role hierarchy, reset email, or account-management screen is required initially.
- Missing or invalid authentication configuration must fail closed with a generic unavailable page. Configuration secrets must never appear in browser output, logs, static exports, or committed examples.
- Apply server-side authorization before every protected page and query. Unauthenticated requests redirect to login without including private content; any return destination must be a validated local admin route. Unknown authenticated booking lookups return an appropriate not-found response.
- Separate admin authentication state from public booking and feedback state. Regenerate the session identifier after successful authentication, use strict session handling and HttpOnly/SameSite cookies with Secure enabled on HTTPS, and invalidate authentication on logout. Use CSRF protection for login and logout; neither action may mutate state through GET.
- Proposed expiry defaults are 30 minutes of inactivity and an eight-hour absolute lifetime. Credential rotation must invalidate existing admin authentication on its next request. Production access requires HTTPS; local HTTP development remains supported.
- Persist failed-login throttling server-side rather than relying on cookies. A proposed default is five failed attempts per client address in 15 minutes, with generic responses that do not reveal whether a username exists. Only trust forwarding headers from explicitly configured proxies. Bound and expire throttle records; do not log passwords or session tokens.
- Default list ordering is visit date descending, then booking identifier descending for stable pagination. Use 25 records per page, bounded server-side. Offer destination, inclusive visit-date range, and reference/name search. Filters persist during pagination and return from details. Date filtering refers to the booking's scheduled visit/arrival date, not every day of an overnight stay.
- Validate filter values and dates server-side and use parameterized queries. Fetch only the current page rather than loading all bookings. Invalid filters show a useful error without widening the query silently.
- List columns contain reference, visitor name, destination, scheduled date and time, attendee count, and feedback availability. Details include stored contact information, confirmation status, creation time, overnight fields when relevant, and any associated response. Preserve historical missing email as “Not provided.”
- Show scheduled times in Kenya local time using readable dates and times without displaying the Africa/Nairobi identifier. Stored UTC timestamps must be converted consistently for display. Avoid ambiguous numeric dates.
- Feedback details show the visitor's attendance answer, rating only when applicable, optional enjoyment/improvement/comments, and submission time. Describe attendance as self-reported feedback, never as staff-verified check-in. Show “No feedback received” separately from “Did not attend.” Do not expose invitation tokens, queued email payloads, or private feedback URLs.
- Escape all stored visitor content and render comments as plain text. Set private pages to no-store and noindex, avoid third-party assets that could receive private referrers, and retain existing private database permissions and location.
- Keep the interface compact: login, bookings list, and booking details with feedback. Use accessible labels, visible focus, clear errors, responsive overflow or stacked records, and server-rendered forms that function without JavaScript. No metrics dashboard is required.
- Admin reads must not alter bookings, send emails, consume feedback tokens, or change invitation eligibility. Preserve existing public booking and feedback flows.
- Apply implementation to the combined main branch and each actual v1, v2, and v3 branch, committing corresponding changes as previously requested. Keep design-preview source compatible, but exclude admin pages, credentials, databases, and private records from the static export. GitHub Pages cannot host this authenticated PHP feature.

## Testing Decisions

- **Owner confirmed:** test login, protected booking and feedback views, and logout through the real PHP application with temporary database storage, including failed logins and expired sessions.
- Prefer the existing HTTP/browser integration seam: start the real PHP server with isolated SQLite and session storage, provision test-only credentials privately, seed representative records, and navigate as a browser user. Reuse the booking, contact, version, and feedback integration-test setup rather than adding a second testing framework.
- Assert observable behavior, not template structure or helper implementations: protected content is inaccessible before login; successful login permits access; filters and pagination return the correct records; details match the selected booking; logout and expiry remove access.
- Cover incorrect credentials, absent configuration, CSRF failures, session fixation prevention, repeated failed attempts across fresh cookies, expiry boundaries, credential rotation, direct detail URLs, and unsafe return destinations. Use a controllable server-side time seam where necessary; never introduce a public clock-control endpoint.
- Cover all destinations, day and overnight visits, missing legacy email, attended and non-attended feedback, no feedback, empty results, invalid filters, unknown records, and stored hostile markup. Verify filters survive pagination and a round trip to details.
- Check mobile layout, keyboard navigation, accessible labels and errors, and core behavior without JavaScript. Exercise shared admin behavior on the combined application and confirm availability in each standalone version.
- Verify authenticated responses are not cacheable and that public booking/feedback sessions do not acquire admin access. Assert admin reads cause no booking, feedback, or mail-queue mutations. Automated tests must not send real email.
- Run existing relevant booking and feedback regressions and static-export checks. Verify exports contain neither admin functionality nor private data or secrets. Any throttle-storage migration must preserve existing bookings, responses, and queued notifications.

## Out of Scope

- Implementation, deployment, or live credential creation during this spec-writing task.
- Registration, multiple roles, staff invitations, self-service password resets, social login, and account-management screens.
- Booking edits, deletion, cancellation, approval, check-in, QR scanning, and capacity management.
- Feedback editing or moderation, public reviews, charts, analytics, CSV exports, and bulk actions.
- Email resending, delivery-status dashboards, Resend webhooks, and changes to feedback invitation timing or eligibility.
- An authenticated admin experience on GitHub Pages or a static admin demo containing real information.
- Redesigning the three visitor-facing versions or inventing a data-retention policy.

## Further Notes

The existing application already stores booking contacts, schedules, Galana overnight details, and one feedback response per booking. It currently has no administrator identity or protected listing interface. This feature exposes those existing records privately without adding an attendance workflow.

The admin view requires the PHP-hosted application and persistent private storage. A combined deployment shows records from its configured database; separate version deployments do not automatically aggregate separate databases.

The owner approved the proposed integration-test coverage. The read-only product scope and concrete defaults remain explicitly proposed so that later implementation does not treat inferred requirements as direct instructions.

## Comments

- Published using the to-spec skill. Test-boundary check confirmed by the owner; no application code changed.
