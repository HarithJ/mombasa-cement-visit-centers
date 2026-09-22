# 01 — Day-visit feedback from email to saved response

**What to build:** A new day booking for Sahajanand Special School, Galana Farm, or Kibarani Feeding Center receives a next-morning email with a private feedback link. The visitor can rate the experience and leave optional comments, or choose “I didn’t attend,” then receive an acknowledgement that their response was saved.

**Blocked by:** None — can start immediately

**Status:** resolved

**Type:** feature

- [ ] Every new eligible day-booking contact is scheduled for an invitation regardless of attendance. Do not require staff check-in or add attendance/cancellation filtering.
- [ ] Schedule for 09:00 Africa/Nairobi on the day after the booked visit by default, with a server-configurable sending time. Treat 09:00 as the spec’s implementation default, not a separately confirmed business requirement. Overnight invitations remain excluded until ticket 02.
- [ ] Use the existing durable email queue and Resend worker with a distinct feedback message kind, one logical invitation per booking, and a due instant separate from retry timing. Preserve existing immediate visitor and destination emails.
- [ ] Use a controlled clock at the existing scheduling/worker boundary for tests. Keep the existing retry protections intact; do not create an unsafe interim delivery implementation while ticket 03 is pending.
- [ ] Generate an unguessable private token, store its hash for lookup, and construct invitation links from a configured canonical HTTPS site URL rather than request headers. Keep API credentials and queued payloads private.
- [ ] Email identifies the destination and scheduled date and contains one clear feedback link. Opening it requires no account and reveals no unnecessary visitor contact information.
- [ ] GET requests, including email scanner requests, do not consume tokens or record feedback. Invalid or tampered tokens show a generic unavailable state. Use CSRF-protected POST for writes and prevent access to another booking’s response.
- [ ] An attended response requires an integer rating from 1 to 5. “I didn’t attend” requires no rating and discards a stale or tampered rating. Enjoyment, improvement and general comments are optional, plain text, and limited to 2,000 characters each.
- [ ] Validation preserves safe entered values and explains corrections. Escape rendered text. Save attendance choice, rating, comments and submission time against the correct booking and destination.
- [ ] Enforce one response per booking transactionally. Double submissions, refreshes and revisiting completed links show an acknowledgement without inserting or overwriting a response.
- [ ] Provide responsive, keyboard-accessible, labelled feedback forms and errors, including a complete no-JavaScript path, in all three designs. Preserve the originating version in links where necessary, with a default for legacy records.
- [ ] Migrations preserve old bookings and queued email payloads, provider IDs, delivery states and retries. Do not send historical bulk invitations or invent email addresses for legacy bookings.
- [ ] An integration test creates a booking through HTTP, advances the clock, captures the invitation through mocked delivery, opens its link and saves feedback. Cover both attendance choices, validation, token isolation, CSRF, replay and migration preservation without sending real email.
- [ ] Commit corresponding changes on main and the actual v1, v2 and v3 branches; update the retained backend source on design-preview. Keep its current static export functional and free of backend data. Sample feedback export is ticket 04.
- [ ] Document the required PHP host, canonical site URL and scheduled worker. No production activation, push or real email send is required by this ticket.

## Answer

Implemented next-morning invitations, private token links, accessible forms, attended/non-attended responses, validation, CSRF protection and immutable one-response-per-booking storage. Application-to-worker integration and all-design no-JavaScript checks pass. Branches: main, v1, v2, v3 and design-preview.
