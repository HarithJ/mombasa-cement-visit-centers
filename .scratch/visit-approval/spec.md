# Visit approval and staff accounts

Status: ready-for-agent
Type: feature

## Problem Statement

Every booking is saved as `automatically_confirmed`, and the visitor is told straight away that their visit "is registered". Nobody at Galana Farm gets to say whether a date works, so visitors may turn up on a day the farm can't host them. The admin panel is read-only and uses a single shared login from environment variables, so the site can't tell who did what.

## Solution

New bookings start as **Pending**. The Galana team (Jaco, Pravin, Giles) and the admins (Harith, Apoorv) each get an email with personal **Accept** and **Reject** buttons. The first person to decide settles the booking, and a rejection needs a reason. The visitor is then told their visit is confirmed, or that it was declined and whom to contact. The visitor never sees the reason. The rest of the team is told who decided. Decisions can also be made, and changed, in `/admin`.

The shared admin login is replaced by individual user accounts stored in the database. Users sign in with a password or an emailed login link, and admins manage accounts on a Users page or with a server command.

## Branches and release

- Build on `galana-3d`, then port to `galana-only`. Leave `main` and `mosaic-theme` alone.
- Commit and push both branches. Don't deploy to jayram.org until the owner says so.
- After deploying, create the five initial accounts on the server (see Users).

## User Stories

1. As a visitor, I want to be told my booking is a request awaiting confirmation, so that I don't travel before it's confirmed.
2. As a visitor, I want an email and SMS when my visit is confirmed, with the same details as today, so that I know I can come.
3. As a visitor whose visit was declined, I want an email and SMS telling me whom to contact, with their name, phone and email, so that I can arrange another date.
4. As a team member, I want Accept and Reject buttons in the new-booking email, so that I can decide without signing in.
5. As a team member, I want a confirmation page behind each button, so that an email scanner opening the link can't decide for me.
6. As a team member rejecting a visit, I want to give a reason only the team sees, so that colleagues understand the decision.
7. As a team member, I want to be told when someone else has decided, so that nobody acts twice.
8. As a team member opening a button after someone else decided, I want to see who decided and when, so that I know where it stands.
9. As a staff user, I want to see each booking's status and filter by it in `/admin`, so that I can find bookings still waiting.
10. As a staff user, I want to confirm, reject or change a decision from the booking's admin page, so that I can fix mistakes or respond to changes like flooding.
11. As a staff user, I want a full decision history on each booking, so that I can see who decided what, when and why.
12. As a staff user, I want my own account, signing in with a password or an emailed login link, so that my actions are recorded under my name.
13. As a staff user who forgot my password, I want a reset link by email, so that I can get back in.
14. As an admin, I want to add, edit, deactivate and reactivate users, so that the team list stays current without editing server settings.
15. As an admin, I want an SMS for every new booking, so that I notice it quickly.
16. As the owner, I want existing bookings to count as confirmed, so that nobody has to review old visits.
17. As the owner, I want the feedback email to go only to confirmed visits, so that rejected or undecided visitors aren't asked about a visit that didn't happen.

## Implementation Decisions

### Booking status

- Statuses: `pending`, `confirmed`, `rejected`. New bookings are saved as `pending`.
- A migration changes every existing `automatically_confirmed` booking to `confirmed`, with no decision history entry and no messages sent.
- One decision covers the whole booking: the visit, plus any overnight stay and transport requested.
- Pending bookings get no reminders and are never decided automatically.
- The post-visit feedback invitation is only created/sent for `confirmed` bookings. A booking that becomes `rejected` after its invitation was queued has that invitation cancelled.

### Deciding through the email buttons

- The new-booking team email goes to every active user. Each recipient gets their own Accept and Reject links containing a secret, unguessable token tied to their user account and the booking. Only a hash of the token is stored.
- Opening a link (GET) never changes anything. It shows the booking summary (reference, visitor, date, time, attendees, overnight, transport) and a final button. Pressing it (POST, CSRF-protected) records the decision.
- Reject requires a reason: plain text, 1–500 characters after trimming, and the page warns that only the team sees it.
- Links don't sign the user in and only act on that one booking.
- **First decision wins.** If the booking is no longer pending, the link shows a status page ("Confirmed by Pravin on 18 Nov at 10:42") and offers no buttons.
- Links stop working after **23:59 Africa/Nairobi on the visit date**. After that they show the status page.
- Links stop working if their user is deactivated or their email address changes.
- The decision is recorded under the link owner's user account.

### Deciding and changing in `/admin`

- The booking list shows a status column and a status filter (All / Pending / Confirmed / Rejected).
- The booking detail page shows the current status and the full decision history: who, when (Kenya time), the decision, and the reason for rejections. It offers:
  - when pending: Confirm and Reject (reason required);
  - when decided: change to the other decision, Confirm ↔ Reject (reason required when the new decision is Reject).
- Any signed-in user can decide or change a decision. It's recorded under that user.
- A decision can never go back to `pending`.
- Decisions and changes are allowed only until 23:59 Africa/Nairobi on the visit date.

### Messages after a decision

Every decision, including each change, sends:

- **To the visitor (email + SMS):**
  - Confirmed: today's booking details (reference, date, time, attendees, transport, overnight, Galana contacts) under a "Your visit is confirmed" heading, without naming who confirmed.
  - Rejected: says the visit was declined and asks the visitor to contact the person who rejected it, giving that user's name, phone and email. The reason is **never** included.
- **To every other active user (email, "team decision"):** who decided, what, when, the booking summary and, for rejections, the reason.

Visitors without an email address get the SMS only.

### Messages when a booking is made

- The on-screen confirmation, the visitor email and the visitor SMS say the request has been received and is **not confirmed yet**.
- The team email goes to all active users with their personal Accept/Reject buttons.
- The new-booking SMS goes to every active **admin** (using the phone number on their account), reworded to say the booking is awaiting confirmation and to check email.
- The hard-coded recipients in `ManagerNotifications.php` and the use of `contacts.php` emails for team notifications are replaced by the users table. `contacts.php` stays as the source for the public Galana contacts shown on the site.

### Wording

All new and changed email, SMS and page wording (pending, confirmed, rejected, team decision, decision pages, status pages, sign-in, login-link, reset and welcome emails) is drafted first and approved by the owner **before implementation starts**.

### Users

- A `users` table replaces `ADMIN_USERNAME` / `ADMIN_PASSWORD_HASH`, which are removed completely from code, docs and the server environment.
- Fields: name, email (unique, case-insensitive), phone, role (`admin` | `team`), active flag, password hash (always set), timestamps.
- **Roles:** everyone has the same booking and decision permissions. `admin` additionally gets the new-booking SMS and the Users page.
- **No delete.** Users are deactivated, so past decisions keep a name attached.
- Deactivating a user, or changing their email, ends their sessions and invalidates their unused login, reset and decision links.
- The last active admin can't be deactivated or changed to `team`, and admins can't deactivate themselves.

### Sign-in

- Sign in with email + password, or **"Email me a login link"**.
- **Login link:** single use, valid 15 minutes, only sent to active accounts. The page always says "If that email has an account, a link is on its way". At most 3 requests per email per 15 minutes.
- **Forgot password:** emails a single-use reset link valid for 1 hour, with the same non-revealing message. Setting a new password ends that user's other sessions.
- Passwords are at least 12 characters.
- Current protections stay: 5 failed sign-ins per direct peer address per 15 minutes, 30-minute idle timeout, 8-hour maximum session, `/admin`-scoped cookie, CSRF on all forms.
- There is no My account page and no change-password feature beyond "Forgot password".

### Users page (admins only)

- List all users with name, email, phone, role and active state.
- Add (name, email, phone, role), edit those fields, deactivate, reactivate.
- A new user gets a random password nobody knows and a **welcome email** with a sign-in link valid for 48 hours, explaining that they can set a password with "Forgot password".

### Server command

- `php bin/users.php` with `list`, `add`, `edit`, `deactivate`, `reactivate`, mirroring the Users page and its safety rules. `add` takes `--no-welcome` to skip the welcome email.
- New accounts get a strong random placeholder password that is never displayed or stored in plain text.

### Initial accounts

Created silently with `bin/users.php add --no-welcome` after deployment. The owner tells people when to start.

| Name | Role | Phone | Email |
|---|---|---|---|
| Harith | admin | +254 792 488382 | harithjaved@gmail.com |
| Apoorv | admin | +254 115 933896 | apoorvchit@gmail.com |
| Jaco | team | 0706 093132 | jangabriel@nyumbagri.com |
| Pravin | team | +254 790 225592 | pravin.kerai@nyumbagri.com |
| Giles | team | +254 728 999990 | giles@nyumba.com |

### Delivery

All emails and SMS go through the existing queues and workers (once a minute), so messages can take up to about a minute to arrive.

## Testing Decisions

- Tests check external behaviour (pages, stored data, queued messages), not internal methods. They use isolated temporary storage, fictional people and the test clock, and send no real messages.
- **Booking:** new bookings are pending, visitor wording says not confirmed, the team email has per-recipient links for every active user, and the admin SMS goes to active admins only.
- **Decision links:**
  - GET changes nothing;
  - POST confirms/rejects;
  - a reason is required and capped at 500 characters;
  - the first decision wins, with a status page afterwards;
  - links expire after 23:59 Nairobi on the visit date;
  - tampered, foreign, deactivated-user and changed-email tokens are refused;
  - CSRF is enforced.
- **Messages:** the visitor gets the confirmed or rejected message, and the rejected message contains the rejecter's contact details and **never** the reason. Other users get the team decision email, which includes the reason.
- **Admin:**
  - status column and filter;
  - confirm, reject and change, with the reason rule and the visit-day cutoff;
  - no return to pending;
  - history shown in order;
  - every change sends messages.
- **Users and sign-in:**
  - password sign-in;
  - login link (single use, 15-minute expiry, request limit, non-revealing message);
  - reset (1-hour expiry, other sessions ended);
  - 12-character minimum;
  - Users page admin-only;
  - add/edit/deactivate/reactivate;
  - last-admin and self-deactivation guards;
  - deactivation/email change ends sessions and links;
  - welcome email;
  - `bin/users.php` parity.
- **Migration:** existing bookings become confirmed, and the old environment-variable login no longer works.
- **Feedback:** invitations only for confirmed bookings; a queued invitation is cancelled on rejection.
- **Prior art:** the admin, bookings, overnight, feedback, email, booking-SMS and manager-notification suites.

## Out of Scope

- `main` and `mosaic-theme`, and the Sahajanand and Feeding Centre destinations.
- Reminders or automatic decisions for pending bookings.
- Returning a booking to pending, or cancelling it as a separate action.
- Capacity checks or accommodation allocation.
- Showing the rejection reason to the visitor.
- A My account page or in-app password change.
- Deploying (until the owner says so).

## Comments

- 2026-11-16: Design agreed with the owner in a question-by-question review. Status stays `needs-info` until the owner approves the drafted wording, then moves to `ready-for-agent`.
- 2026-11-16: Owner approved [wording.md](wording.md) as drafted, including its four open points as written (transport/overnight described as confirmed; "usually within a day" kept; one short admin SMS; sign-in footer names Harith or Apoorv). Status → `ready-for-agent`.
