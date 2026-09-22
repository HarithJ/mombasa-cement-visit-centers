# Post-visit feedback invitations

Status: ready-for-agent
Type: feature

## Problem Statement

Nyumba Group collects visitor email addresses and registers visits to Sahajanand Special School, Galana Farm, and Kibarani Feeding Center, but has no automated way to ask visitors about their experience after their scheduled visit. Staff would otherwise have to identify completed visit dates, email people manually, and connect replies to their bookings.

The owner explicitly wants invitations sent regardless of whether the visitor attended. Attendance confirmation must not become a prerequisite or an operational burden.

## Solution

Automatically email every eligible booked visitor a private link to a short feedback form on the PHP-hosted website. Send the invitation the morning after the scheduled day visit, or the morning after departure for Galana overnight bookings. Visitors can rate their experience and leave comments, or choose “I didn’t attend” without being asked to rate an experience they did not have.

Record each response against its booking and destination. Send one invitation per booking, reuse the existing Resend delivery infrastructure, and preserve existing booking confirmations. Make the experience available across all three designs and their standalone branches.

## User Stories

1. As a booked visitor, I want a feedback invitation after my scheduled visit, so that I can share my experience while it is fresh.
2. As a visitor who did not attend, I want to receive the invitation too, so that I can report that I did not attend.
3. As a visitor who did not attend, I want an “I didn’t attend” option, so that I do not have to submit a misleading rating.
4. As a day visitor, I want the invitation the next morning, so that it does not interrupt my visit.
5. As a Galana overnight visitor, I want the invitation after my scheduled departure, so that I can reflect on the whole stay.
6. As a visitor, I want the email to identify my destination and visit date, so that I understand which booking it concerns.
7. As a visitor, I want one clear link in the email, so that I can reach the feedback form easily.
8. As a visitor, I want to open my private link without creating an account, so that giving feedback is quick.
9. As a visitor, I want to rate my overall experience from one to five stars, so that I can give a simple assessment.
10. As a visitor, I want to describe what I enjoyed, so that the team knows what worked well.
11. As a visitor, I want to suggest improvements, so that future visits can be better.
12. As a visitor, I want space for optional comments, so that I can share anything the other questions missed.
13. As a visitor, I want to submit a rating without writing comments, so that the form remains short.
14. As a visitor, I want clear validation that preserves what I typed, so that I can correct an error without starting again.
15. As a mobile visitor, I want a readable form with easy-to-use controls, so that I can respond from my phone.
16. As a keyboard or screen-reader user, I want labelled controls and understandable errors, so that I can submit feedback independently.
17. As a visitor without JavaScript, I want the form to remain usable, so that browser limitations do not prevent my response.
18. As a visitor, I want a clear acknowledgement after submission, so that I know my feedback was saved.
19. As a visitor who revisits a submitted link, I want to see that my response was received, so that I do not accidentally submit twice.
20. As a visitor, I want my private feedback link to avoid exposing my email address or phone number in its URL, so that it reveals as little personal information as possible.
21. As a destination operator, I want feedback associated with the correct booking and destination, so that responses have useful context.
22. As a destination operator, I want non-attendance stored distinctly from an experience rating, so that it cannot be mistaken for a poor review.
23. As a destination operator, I want invitations sent without recording check-ins, so that collecting feedback requires no attendance workflow.
24. As an operator, I want repeated scheduler runs and booking submissions to avoid duplicate invitations, so that visitors are not contacted repeatedly.
25. As an operator, I want temporary email failures retried safely, so that invitations are not lost during provider outages.
26. As an operator, I want uncertain delivery attempts to stop before duplicate protection expires, so that retries do not create duplicate emails.
27. As an operator, I want scheduling based on Kenya time, so that “the next morning” matches the destinations’ local day.
28. As an operator, I want overdue invitations picked up after a scheduler interruption, so that a short outage does not permanently skip visitors.
29. As an operator, I want legacy bookings without email addresses handled safely, so that the feature does not break existing records or invent recipients.
30. As an operator, I want immediate booking emails to continue working, so that feedback invitations complement the current booking process.
31. As a maintainer, I want the feature present in all three versions and their actual branches, so that none of the maintained designs falls behind.
32. As a design-preview reviewer, I want to try a clearly labelled sample feedback form without sending or saving anything, so that the static preview remains safe to explore.

## Implementation Decisions

- **Confirmed requirement:** invite booked visitors regardless of actual attendance. Do not introduce an attendance gate, check-in requirement, or no-show exclusion. Do not introduce cancellation-based filtering into this feature; the current booking model has no cancellation workflow.
- **Timing:** use the morning after the visit date for day bookings and the morning after departure for Galana overnight bookings. Africa/Nairobi is the authoritative timezone. A concrete implementation default is 09:00 local time, configurable on the server. The precise hour was not chosen by the owner and is a documented default rather than an approved business requirement.
- **Eligibility and rollout:** create an invitation schedule for each new confirmed booking with a valid email address after the feature is enabled. No automatic historical bulk mailing on deployment. Legacy records remain intact; historical backfill is outside this release. “Every visitor” means every eligible booking contact, not every individual attendee, since only the booking contact's email is collected.
- Extend booking persistence and the durable notification queue to support a feedback invitation distinct from the current destination notification and visitor confirmation. Enforce one logical feedback invitation per booking at the database level.
- Persist the due instant separately from retry timing. Scheduling future mail must not start the provider's idempotency retention window early. First-attempt time begins only when delivery is actually attempted.
- Reuse the scheduled email worker, Resend client, immutable retry payloads, database claiming/lease mechanism, stable idempotency keys, and existing bounded retry policy. Catch up on due invitations after worker downtime. Do not claim delivery when the provider has only accepted the request.
- Retain a separate feedback-sending enable switch so operators can stop invitations without disabling immediate booking confirmations. Require a configured canonical HTTPS website URL; never construct email links from an untrusted request host header. Keep all credentials server-side.
- Issue a cryptographically random, unguessable invitation token. Store a hash for lookup and associate it with its booking; the private queued email payload necessarily contains the invitation URL. Do not encode personal information or a sequential booking identifier as the access credential.
- A feedback GET request displays the form without consuming the token or submitting feedback; email security scanners must not mark a response as completed. Use POST for submission with CSRF protection. Invalid or unknown links show a generic unavailable message without disclosing booking details.
- No arbitrary token expiry is required for the first release. A submitted token remains usable only for an acknowledgement. Expiry and data-retention policy are not invented in this spec.
- Store one response per booking, including attendance response, nullable overall rating, optional enjoyment/improvement/general comments, and submission timestamp. Use a uniqueness constraint and transactional write to prevent duplicate responses under retries and simultaneous requests.
- For an attended visit, require an integer rating from one to five. For “I didn’t attend,” require no rating and discard any stale or tampered rating. Free-text answers are optional; use a documented limit of 2,000 characters per answer with server-side enforcement. This limit is an implementation default.
- Treat comments as plain text, escape output, and preserve safe user input after validation errors. Never expose responses through a public listing. The invitation token grants access only to its own minimal feedback form; do not show visitor phone or email unnecessarily.
- After successful submission, redirect to a thank-you state. Repeat submissions and refreshed pages must not create or overwrite a response. Editing feedback is outside the first release.
- Check for an existing response before a not-yet-attempted invitation is sent. Suppress unnecessary new sends for completed feedback. Do not alter an immutable request after an ambiguous provider attempt; reconcile it through the existing retry/review rules.
- Support all three PHP designs on the combined application and each standalone version branch. Preserve a booking's originating version where needed so the email link returns to a consistent design; older records may use the default design.
- Keep static previews explicitly local-only. If a sample feedback preview is exported, it must use fictional sample data and no real tokens, API calls, booking reads, or response persistence. Do not put a nonfunctional production invitation link into the static preview.
- Implementation must commit corresponding changes to each actual version branch and the combined main branch, and update the design-preview source/export behavior. Pushing or activating production email is not part of spec publication.

## Testing Decisions

- Test observable behavior: who is invited, when a message is sent, what link the visitor receives, whether the form can be completed, and whether the response is saved once. Avoid tests that merely mirror helper methods, internal SQL layout, or CSS implementation.
- Prefer one primary integration boundary around the real PHP application and scheduled worker: create a booking through HTTP, advance a controlled clock, run the worker against a fake mail sender, open the captured invitation link in a browser, and submit feedback against isolated SQLite storage. Do not send real email in automated tests.
- Reuse existing temporary database/session setup, browser tests for all versions and no-JavaScript submissions, and the worker's injectable delivery callback. Introduce an injectable clock at the scheduling/worker boundary to test time deterministically instead of waiting or relying on the wall clock. Avoid a public test-only endpoint.
- Cover day and overnight scheduling, exact due-time boundaries, Africa/Nairobi calendar transitions, non-attendance eligibility, catch-up after downtime, disabled invitations, and missing legacy email addresses. Departure takes precedence over arrival/visit date for overnight stays.
- Cover scheduler reruns, booking replay, concurrent claims, provider timeout, retry backoff, an immutable link/payload across retries, provider acceptance, and movement to review before the provider's idempotency window expires.
- Cover attended and non-attended responses, missing/invalid/out-of-range ratings, optional comments, excessive comment length, hostile markup, stale fields after choosing non-attendance, unknown/tampered tokens, CSRF failure, double-click submission, refresh, and revisiting a completed link.
- Verify that merely loading the feedback link does not consume it, and that one invitation cannot retrieve or change another booking's response.
- Verify responsive layouts, keyboard controls, accessible labels/errors, and no-JavaScript completion across all three designs. Verify that static preview interactions never transmit personal data or issue email requests.
- Migration checks must preserve existing bookings, visitor emails, queued immediate notifications, provider IDs, retry states, and already accepted messages. The new feature must not resend immediate confirmations or auto-mail historical bookings.
- The proposed test boundaries were presented to the owner using the skill's required check. Unless changed by the owner, use the existing application/worker boundary with focused timing and retry cases described above; this is not a separate approval gate.

## Out of Scope

- Implementing or activating the feature as part of this spec-writing task.
- Attendance tracking, staff check-in, QR codes, cancellation workflows, or restricting invitations to attendees.
- Reminder campaigns, repeated invitations, marketing emails, incentives, or contacting individual attendees whose addresses were not collected.
- Historical invitation backfills, automatic import of old contact details, or retroactively notifying all old bookings.
- Feedback editing, public reviews, dashboards, analytics, exports, staff accounts, response moderation, or automatic emails containing submitted feedback to staff.
- New hosting selection or deployment, GitHub Pages backend execution, real email test sends, Resend webhook/bounce processing, and guarantees of inbox delivery.
- Inventing a token-expiration or data-retention policy, changing the existing destination schedules, or redesigning the three existing websites.

## Further Notes

The user's latest business decision is authoritative: invitation eligibility must not depend on attendance. The prior suggestion to introduce staff check-in was not accepted and must not be implemented as a dependency.

The required visitor email field, immediate visitor/destination Resend notifications, and destination contact details already exist in the current codebase. Email sending requires production PHP hosting, persistent private storage, verified Resend configuration, and a running scheduled worker. The static GitHub Pages website remains a design preview.

The next-morning flow, short form, private links, and “I didn’t attend” option synthesize the discussed proposal. Defaults for the exact sending hour, text limits, rollout, and route/version handling make the implementation actionable without presenting them as additional user-approved requirements.

## Comments

- Published from the conversation using the to-spec skill. Test-boundary check offered asynchronously; no additional product interview required.
