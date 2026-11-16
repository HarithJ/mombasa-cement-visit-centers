# Visit approval: wording for approval

Status: ready-for-agent
Spec: [spec.md](spec.md)

Placeholders in `{braces}` are filled in per booking. Dates look like "Sat 22 Nov 2026" in emails and "22 Nov 2026" in SMS. Times look like "10:00 am EAT". "Scenario" means day / day + transport / overnight / overnight + transport, as today.

Mark anything you want changed. The **Questions** section at the end lists the points where I made a call you might not agree with.

---

## 1. Booking form and on-screen confirmation (visitor)

**Unchanged:** "It takes a minute, and you'll get a booking reference straight away."

**Confirmation panel**

- Eyebrow: YOUR VISIT, AT A GLANCE *(unchanged)*
- Heading: **Request received.** *(was "Visit registered.")*
- Subtitle: We've sent your request to the Galana team. They'll confirm by email and SMS, usually within a day. Please don't travel until your visit is confirmed.
- Highlighted line: **Status: Awaiting confirmation** *(replaces "Registration acknowledges your visit; it does not imply a capacity check.")*
- Details list: unchanged.
- Contacts block: unchanged ("Questions about your visit?").

## 2. Visitor email: request received

- Subject: Your request to visit Galana Farm — {reference}
- Eyebrow: Awaiting confirmation
- Title: We've received your request.
- Paragraphs:
  - Hi {name},
  - Thanks for asking to visit Galana Farm on {date} at {time}. The Galana team will check the date and confirm by email and SMS, usually within a day.
  - Scenario line:
    - day: *(no extra line)*
    - day + transport: You've also asked for transport. We'll confirm it together with your visit.
    - overnight: You've also asked to stay overnight. We'll confirm it together with your visit.
    - overnight + transport: You've also asked for transport and an overnight stay. We'll confirm both together with your visit.
- Details: unchanged, plus a row **Status: Awaiting confirmation**.
- Note: Your visit isn't confirmed yet. Please don't travel until you hear from us.
- Contacts heading: Questions before your visit? *(unchanged)*
- Footer: Please keep your booking reference: {reference} *(unchanged)*

## 3. Visitor SMS: request received

- day: Hi {name}, thanks for your request to visit Galana Farm on {date} at {time}. It isn't confirmed yet; we'll let you know shortly. Ref {reference}.
- day + transport: Hi {name}, thanks for your request to visit Galana Farm on {date} at {time}, with transport. It isn't confirmed yet; we'll let you know shortly. Ref {reference}.
- overnight: Hi {name}, thanks for your request to visit Galana Farm on {date} at {time} and stay overnight. It isn't confirmed yet; we'll let you know shortly. Ref {reference}.
- overnight + transport: Hi {name}, thanks for your request to visit Galana Farm on {date} at {time}, with transport and an overnight stay. It isn't confirmed yet; we'll let you know shortly. Ref {reference}.

## 4. Team email: new booking to decide (one per user, with their own buttons)

- Subject: Please confirm: visit to Galana Farm on {date} — {reference}
- Eyebrow: Needs a decision
- Title: A new visit to confirm.
- Paragraphs:
  - Hi {user first name},
  - {visitor name} has asked to visit Galana Farm on {date} at {time} for a total of {people}.
  - Scenario line:
    - day: No transport or overnight stay was requested.
    - day + transport: They've asked for company transport.
    - overnight: They've asked to stay overnight.
    - overnight + transport: They've asked for company transport and to stay overnight.
  - Please accept or reject the visit. The first person to decide settles it, and everyone else is told.
- Details: as today (reference, date, time, attendees, transport, overnight, visitor, organization, phone, email).
- Buttons: **Accept visit** · **Reject visit**
- Secondary link: Call the visitor *(unchanged)*
- Note: These buttons are personal to you; please don't forward this email. They work until 23:59 on {date}. You can also decide from the admin panel.

## 5. Admin SMS: new booking (to active admins)

One template for all scenarios, with the requests listed:

> New visit request: {visitor name}, Galana Farm, {date} at {time}, {people}{, transport}{, overnight}. Awaiting confirmation; see your email to accept or reject. Ref {reference}.

## 6. Decision pages (from the email buttons, no sign-in)

Page title: Visit {reference} · Galana Farm

**Booking summary (on every decision page)**

- {visitor name} ({organization, if any})
- {date} at {time} · {people}
- Transport: Requested / Not requested
- Overnight stay: Requested *(only if requested)*
- Phone {phone} · Email {email}

**Accept page**

- Heading: Accept this visit?
- Text: {visitor name} will be told by email and SMS that their visit is confirmed. The rest of the team will be told you accepted it.
- Button: **Yes, accept visit**
- Link: Reject instead

**Reject page**

- Heading: Reject this visit?
- Label: Reason for rejecting (required)
- Hint: Only the team sees this. The visitor won't see it. They'll be asked to contact you, {user name}, on {user phone} or {user email}.
- Character counter: {n}/500
- Button: **Reject visit**
- Link: Accept instead
- Errors:
  - Please give a reason for rejecting this visit.
  - Please keep the reason to 500 characters or fewer.

**Done pages**

- After accepting: **Visit confirmed.** We've told {visitor name} and the rest of the team. You can close this page.
- After rejecting: **Visit rejected.** We've told {visitor name} to contact you, and shared your reason with the rest of the team. You can close this page.

**Status page (someone already decided, or the link has expired)**

- Heading:
  - **Already confirmed.** (if confirmed)
  - **Already rejected.** (if rejected)
  - **Still awaiting a decision.** (if pending but the link expired)
- Line:
  - Confirmed by {decider} on {date} at {time}.
  - Rejected by {decider} on {date} at {time}.
- Expired line: This link stopped working at 23:59 on the visit date.
- Admin line: To change a decision, sign in to the admin panel. *(link)*

**Invalid link page**

- Heading: This link doesn't work.
- Text: It may be incomplete, or it's no longer valid. Sign in to the admin panel to see the booking. *(link)*

## 7. Visitor email + SMS: visit confirmed

**Email**

- Subject: Your visit to Galana Farm is confirmed — {reference}
- Eyebrow: Your visit is confirmed
- Title: We look forward to welcoming you.
- Paragraphs:
  - Hi {name},
  - Good news: your visit to Galana Farm on {date} at {time} is confirmed.
  - Scenario line:
    - day: Keep your booking reference handy, and get in touch if you have any questions before you arrive.
    - day + transport: Your transport is confirmed too. Our team will contact you about pickup details.
    - overnight: Your overnight stay is confirmed too. Our team will contact you if there's anything to arrange.
    - overnight + transport: Your transport and overnight stay are confirmed too. Our team will contact you about pickup details.
- Details: as today, with a row **Status: Confirmed** (the transport and overnight rows say "Confirmed" instead of "Requested (subject to availability)").
- Contacts and footer: unchanged.

If the visit was rejected earlier and is now confirmed, the second paragraph reads: "Good news: we've been able to confirm your visit to Galana Farm on {date} at {time} after all." The subject is the same.

**SMS**

> Hi {name}, your visit to Galana Farm on {date} at {time} is confirmed{, including transport}{, including your overnight stay}{, including transport and your overnight stay}. We look forward to welcoming you! Ref {reference}.

## 8. Visitor email + SMS: visit declined

**Email**

- Subject: About your visit request to Galana Farm — {reference}
- Eyebrow: Visit not confirmed
- Title: We can't host your visit on this date.
- Paragraphs:
  - Hi {name},
  - Sorry, we're unable to confirm your visit to Galana Farm on {date} at {time}.
  - Please contact {decider name} to talk about it or find another date:
- Contact box: {decider name} · {decider phone} · {decider email}
- Details: reference, date, time, attendees, with a row **Status: Not confirmed**.
- Footer: Please quote your booking reference: {reference}

If the visit was confirmed earlier and is now declined:

- Subject: Change to your visit to Galana Farm — {reference}
- Title: We can no longer host your visit on this date.
- Second paragraph: Sorry, we're no longer able to host your visit to Galana Farm on {date} at {time}, which we'd confirmed earlier.

**SMS**

> Hi {name}, sorry, we can't confirm your visit to Galana Farm on {date}. Please contact {decider name} on {decider phone} or {decider email}. Ref {reference}.

The confirmed-then-declined SMS reads "…we can no longer host your visit to Galana Farm on {date}…".

## 9. Team decision email (to every other active user)

**Confirmed**

- Subject: Confirmed by {decider}: visit on {date} — {reference}
- Eyebrow: Visit confirmed
- Body: {decider} confirmed {visitor name}'s visit to Galana Farm on {date} at {time} ({people}). {visitor name} has been told by email and SMS. No action needed.

**Rejected**

- Subject: Rejected by {decider}: visit on {date} — {reference}
- Eyebrow: Visit rejected
- Body: {decider} rejected {visitor name}'s visit to Galana Farm on {date} at {time} ({people}). The visitor has been asked to contact {decider}.
- Reason box: **Reason:** {reason}

**Changed** (either direction): the subject starts with "Changed by {decider}:", and the first line becomes "{decider} changed {visitor name}'s visit … from confirmed to rejected" (or the reverse).

All three emails include the booking details and a link: "View in admin panel". They have no buttons.

## 10. Admin panel

**Status labels**

- Awaiting confirmation (pending)
- Confirmed
- Rejected

**Booking list**

- New column: **Status**
- New filter: **Status**, with the options All · Awaiting confirmation · Confirmed · Rejected

**Booking detail panel**

- Heading: Decision
- Pending:
  - Text: This visit is awaiting confirmation.
  - Buttons: **Confirm visit** · **Reject visit**
- Confirmed:
  - Text: Confirmed by {decider} on {date} at {time}.
  - Button: **Change to rejected**
- Rejected:
  - Text: Rejected by {decider} on {date} at {time}.
  - Reason: {reason}
  - Button: **Change to confirmed**
- Reject form:
  - Label: Reason for rejecting (required)
  - Hint: Only the team sees this. The visitor will be asked to contact you.
  - Buttons: Reject visit · Cancel
- Confirmation prompt (inline, before saving): {Confirm/Reject} this visit? The visitor and the rest of the team will be told.
- After the visit date: Decisions can no longer be changed, because the visit date has passed. *(no buttons)*
- Saved messages:
  - Visit confirmed. The visitor and the team have been told.
  - Visit rejected. The visitor and the team have been told.
- Conflict message: Someone else decided this visit while you were looking at it. Here's the latest status.

**History** (newest first)

- Heading: History
- Rows:
  - {date}, {time}: Confirmed by {name}
  - {date}, {time}: Rejected by {name}. Reason: {reason}
  - {date}, {time}: Changed to confirmed by {name}
  - {date}, {time}: Changed to rejected by {name}. Reason: {reason}
- Existing bookings: "Confirmed before visit approval was introduced."

## 11. Sign-in pages

**Sign-in page**

- Eyebrow, heading and intro: unchanged (Your workspace · Welcome back. · Sign in to review bookings and visitor feedback.)
- Field: **Email** *(was "Username")*
- Field: Password
- Primary button: **Sign in**
- Divider: or
- Secondary button: **Email me a sign-in link**
- Link under password: Forgot password?
- Footer note: For authorised team members. Need access? Ask Harith or Apoorv.
- Errors:
  - That email and password don't match. *(wrong details; doesn't say which)*
  - Too many attempts. Please wait 15 minutes and try again.

**After requesting a sign-in link**

- Heading: Check your email.
- Text: If {email} has an account, we've sent a sign-in link. It works once and expires in 15 minutes.
- Link: Back to sign in

**Forgot password page**

- Heading: Reset your password.
- Text: Enter your email and we'll send you a link to set a new password.
- Button: Send reset link
- Result: If {email} has an account, we've sent a reset link. It expires in 1 hour.

**Set new password page**

- Heading: Set a new password.
- Fields: New password · Confirm new password
- Hint: At least 12 characters.
- Button: Save password
- Errors:
  - Use at least 12 characters.
  - The passwords don't match.
- Success: Password saved. You're signed in, and any other sessions have been signed out.

**Expired or used link page** (sign-in or reset)

- Heading: This link has expired.
- Text: Links work once and only for a short time. Request a new one below.
- Button: Back to sign in

## 12. Account emails

**Sign-in link**

- Subject: Your sign-in link for Visit Nyumba admin
- Body: Hi {first name}, here's your link to sign in. It works once and expires in 15 minutes.
- Button: **Sign in**
- Footer: If you didn't ask for this, you can ignore this email.

**Password reset**

- Subject: Reset your Visit Nyumba admin password
- Body: Hi {first name}, use this link to set a new password. It works once and expires in 1 hour.
- Button: **Set a new password**
- Footer: If you didn't ask for this, you can ignore this email. Your password won't change.

**Welcome** (new users added on the Users page)

- Subject: You've been added to Visit Nyumba admin
- Body:
  - Hi {first name}, {admin name} has given you access to the Visit Nyumba admin panel, where you can see bookings and accept or reject visits to Galana Farm.
  - From now on you'll get an email with Accept and Reject buttons whenever someone books a visit.
- Button: **Sign in** *(works once, valid 48 hours)*
- Note: After that, sign in with "Email me a sign-in link", or set a password with "Forgot password?".

## 13. Users page (admins only)

**List and form**

- Navigation item: **Users**
- Heading: Users
- Columns: Name · Email · Phone · Role · Status
- Role values: Admin, Team
- Status values: Active, Deactivated
- Button: **Add user**
- Add/edit form fields: Name · Email · Phone · Role
- Role hint: Admins also get an SMS for every new booking and can manage users.
- Buttons:
  - Add user *(sends the welcome email)*
  - Save changes
  - Deactivate
  - Reactivate
- Deactivate prompt: Deactivate {name}? They'll be signed out and stop getting booking emails. Their past decisions stay on record.

**Saved messages**

- {name} added. We've sent them a welcome email.
- Changes saved.
- {name} deactivated.
- {name} reactivated.

**Errors**

- Please enter a name.
- Please enter a valid email address.
- Someone already uses that email address.
- Please enter a valid phone number.
- You can't deactivate your own account.
- There must be at least one active admin.

## 14. Website copy elsewhere

- FAQ "Can we stay the night?" and "Can you arrange transport?" are unchanged. They already say the team will confirm.
- The FAQ "How do I cancel or change my booking?" is unchanged.

---

## Questions

1. **Transport and overnight in the confirmed message.** Since one decision covers the whole booking, I've written "Your transport/overnight stay is confirmed too. Our team will contact you about pickup details." Do you want them described as **confirmed**, or kept as "requested, the team will contact you"?
2. **"Usually within a day"** (sections 1 and 2) promises a response time. Keep it, or drop it?
3. **Admin SMS** (section 5) is one short template instead of today's four longer ones. OK?
4. **Footer note on sign-in** names "Harith or Apoorv". OK, or keep "Contact your site administrator"?

## Comments

- 2026-11-16: Approved by the owner as drafted, including the four questions above as written.
