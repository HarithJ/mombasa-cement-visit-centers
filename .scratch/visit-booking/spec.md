# Nyumba visitor booking website

Status: ready-for-agent

## Problem Statement

Visitors need a straightforward way to discover and book visits to Sahajanand School, Galana, or the Feeding Centre. Nyumba needs a separate, professionally branded website that captures individual and group visit details, including Galana overnight requests, without requiring a complex technology stack or an administrator to process each initial booking.

## Solution

Create a responsive standalone website with Nyumba Group, Nyumba Foundation, and Nyumba Agri logos at the top; three photography-led destination columns; and a Book visit button for each destination. Each button opens a booking form with that destination selected. Visitors provide their name, phone number, attendee count, date, and time slot. Galana visitors can also request an overnight stay and supply arrival/departure dates and overnight guest count. Valid bookings are stored in SQLite and automatically confirmed on screen.

Use PHP, server-rendered HTML, CSS, and a small amount of JavaScript. Keep booking logic separate from presentation and storage so management functionality can be added later without building an admin interface in this version.

## User Stories

1. As a visitor, I want to recognize the Nyumba Group, Nyumba Foundation, and Nyumba Agri logos, so that I know whose website I am using.
2. As a visitor, I want a clear explanation of the website's purpose, so that I understand that I can book a visit.
3. As a visitor, I want to compare all three destinations, so that I can choose where to go.
4. As a visitor, I want authentic photographs of Sahajanand School, so that I can understand the destination.
5. As a visitor, I want authentic photographs of Galana, so that I can understand the destination.
6. As a visitor, I want authentic photographs of the Feeding Centre, so that I can understand the destination.
7. As a desktop visitor, I want destination photographs to change on hover, so that I can see another view without leaving the page.
8. As a touch-device visitor, I want access to alternate destination photographs without hover, so that the experience works on my device.
9. As a visitor, I want a clearly visible Book visit button on every destination, so that I know how to begin.
10. As a visitor, I want the destination I clicked to be preselected, so that I do not need to enter it again.
11. As a visitor, I want to change the destination using a dropdown, so that I can correct my selection.
12. As a visitor, I want to provide my full name, so that my booking identifies me.
13. As a visitor, I want to provide a phone number, so that the organization has contact information for my visit.
14. As a group organizer, I want to specify the number of attendees, so that the booking represents my group.
15. As a visitor, I want to select a visit date, so that the booking records when I plan to come.
16. As a visitor, I want to select a destination-specific time slot, so that the visit has a clear scheduled time.
17. As a Feeding Centre visitor, I want the 11 am slot to be shown, so that I can book around the supplied visit time.
18. As a visitor, I want past dates to be rejected, so that I do not accidentally create an unusable booking.
19. As a visitor, I want required fields and constraints to be clear, so that I can complete the form correctly.
20. As a visitor, I want understandable validation errors beside the relevant fields, so that I can correct mistakes.
21. As a visitor, I want valid entries preserved after an error, so that I do not have to re-enter the form.
22. As a Galana visitor, I want an overnight-stay checkbox, so that I can indicate that I plan to stay.
23. As an overnight Galana visitor, I want to enter arrival and departure dates, so that my requested stay is recorded.
24. As an overnight Galana visitor, I want to enter the number of overnight guests, so that the accommodation request represents my party.
25. As an overnight Galana visitor, I want invalid date ranges to be rejected, so that the recorded stay is coherent.
26. As a day visitor, I want irrelevant overnight fields hidden and not required, so that the form stays simple.
27. As a visitor changing away from Galana, I want overnight details excluded from the booking, so that irrelevant information is not retained.
28. As a visitor, I want to book without providing an ID card number, so that unnecessary sensitive information is not collected.
29. As a visitor, I want a successful booking confirmed immediately on screen, so that I know the submission worked.
30. As a visitor, I want a booking reference and a summary of my submitted details, so that I can save the confirmation.
31. As a visitor, I want failures distinguished from successful bookings, so that I am not falsely reassured.
32. As a visitor, I want accidental double submissions and confirmation refreshes not to create duplicate bookings, so that one request creates one record.
33. As a mobile visitor, I want readable layouts and comfortable touch targets, so that I can book from my phone.
34. As a keyboard user, I want to navigate photographs, buttons, and form controls, so that I can use the site without a mouse.
35. As a screen-reader user, I want descriptive image text, field labels, and announced errors, so that I can understand and complete the booking.
36. As a visitor sensitive to motion, I want reduced-motion preferences respected, so that transitions do not impede access.
37. As a visitor, I want a concise explanation of how my submitted details are used, so that I understand what I am sharing.
38. As a visitor, I want clear contact information in the footer, so that I can ask questions outside the booking flow.
39. As the site owner, I want bookings persisted reliably, so that visitor submissions survive application restarts.
40. As the site owner, I want schedules and destination content configurable, so that placeholder information can be replaced without redesigning the application.
41. As the site owner, I want the database and private configuration inaccessible from the public web, so that booking data is protected.
42. As a future maintainer, I want distinct presentation, booking, and persistence responsibilities, so that booking management can be added later.

## Implementation Decisions

### Confirmed scope and technology

- Build a separate website on an existing separate domain; the actual domain and hosting provider are not yet supplied.
- Use PHP and SQLite. Do not introduce Django, Next.js, a separate frontend service, or a separate API service.
- Use server-rendered HTML and modern CSS with minimal JavaScript for photo transitions and conditional form behavior.
- Present all three supplied brand logos at the top. Use the existing Nyumba Foundation page as a branding reference: https://www.nyumba.com/nyumba-foundation/.
- Use owner-approved photographs, which the owner says are available. Asset files still need to be supplied; do not invent documentary photographs or factual claims.
- Show three destination columns on desktop and adapt the layout for smaller screens. Every destination includes photography, its name, concise description, and a Book visit button.
- Use a spacious, contemporary professional design with readable sans-serif typography, neutral backgrounds, strong contrast, and one brand-compatible accent color. Keep navigation, animation, borders, and shadows restrained.
- Clicking Book visit must display the form with the relevant destination selected. A dedicated form page versus an accessible dialog remains a presentation choice; either must meet the same behavior and accessibility requirements.
- Automatically confirm valid submissions on screen. Do not require approval, email addresses, ID numbers, or outbound notifications.
- Support total attendee count and Galana overnight arrival date, departure date, and guest count.
- Use placeholder schedules for now. Feeding Centre must include 11:00 am; other placeholder values must be centralized and clearly identified as provisional in setup documentation.

### Proposed implementation defaults

These resolve details not explicitly settled in conversation and can be adjusted without changing the agreed scope.

- Use Africa/Nairobi for visit-date validation and displayed scheduling times. Store creation timestamps consistently in UTC.
- Treat attendee count as a positive integer including the booking contact. Overnight guest count must be positive and no greater than total attendees.
- For overnight Galana bookings, arrival date matches the selected visit date and departure date must be later than arrival. One or more nights are permitted; no invented maximum stay is imposed.
- Validate phone numbers server-side using a documented, permissive policy supporting Kenyan local and international formats. Do not require only a Kenyan number without further agreement.
- Crossfade to an alternate destination image on pointer hover and keyboard focus. Provide a labeled tap control for touch devices. Respect reduced-motion preferences and avoid essential information available only on hover.
- Confirmation includes a non-sequential, hard-to-guess booking reference, destination, visit date/time, attendee count, and applicable overnight details. Mask the phone number in the displayed summary and avoid exposing visitor details in URLs.
- Automatic confirmation acknowledges a successfully recorded booking. Until real schedules and accommodation capacities are supplied, copy must not claim capacity checks or guaranteed accommodation allocation.
- Provide a small privacy explanation using only truthful statements about the implemented data flow. Do not invent a legal policy, retention period, or organizational contact details.

### Application boundaries and storage

- Separate public page rendering, destination/schedule configuration, booking validation and creation, and SQLite persistence. Keep the booking-creation boundary reusable by a future management interface.
- Model destinations, their slot definitions, and bookings with stable identifiers. A slot selection must belong to the chosen destination.
- Persist a booking reference, full name, normalized phone number, destination, visit date, selected time, attendee count, automatic-confirmation status, creation timestamp, and optional Galana overnight dates/guest count. Preserve the booked time even if slot configuration later changes.
- Overnight fields are nullable for day visits and never stored for non-Galana destinations. Validate this on the server, not only through hidden form controls.
- Provide repeatable database initialization and schema migrations without silently deleting existing records.
- Use PDO prepared statements, server-side input validation, output escaping, CSRF protection, and transactional writes. Prevent duplicate submissions through a server-enforced submission token and redirect-after-success flow.
- A failed database write must not display confirmation. Unexpected failures return a safe message, retain recoverable form input where practical, and log diagnostics without full personal details.
- Keep SQLite, private configuration, logs, and backups outside the public document root. Use environment-specific configuration and document required PHP extensions, writable storage, HTTPS, and backup requirements without choosing a hosting provider.
- Do not build administration, exports, capacity management, or booking retrieval endpoints merely to support future extensibility.

## Testing Decisions

- A good test verifies externally observable behavior: what the visitor can select, submit, correct, and see, and whether a real booking is recorded. Avoid assertions about private functions, CSS implementation, or internal query structure.
- Primary proposed seam: browser-level end-to-end tests against the real PHP application with an isolated temporary SQLite database. Exercise the public destination-selection and booking workflow rather than mocking persistence.
- The user explicitly approved this browser-level testing seam against the real PHP application and an isolated temporary SQLite database.
- There is no application code, test framework, or similar test prior art in this repository. Choose the smallest suitable browser-test runner during implementation and keep its runtime development-only.
- Test booking creation from each destination, destination dropdown changes, valid day visits, group attendee counts, and automatic on-screen confirmation.
- Test Feeding Centre's 11 am slot and rejection of unknown destinations or slot/destination mismatches.
- Test empty required fields, malformed phone input, nonpositive/fractional attendee counts, past dates, and preservation of valid input after errors.
- Test Galana overnight visibility, valid stays, departure not after arrival, guest count exceeding attendee count, and removal of overnight data when changing destination or disabling the checkbox.
- Verify persistence across a PHP process restart using the isolated database. Read database state only as a persistence assertion, not as a new production endpoint.
- Test duplicate clicks, replayed submissions, and confirmation refreshes against the same external booking seam.
- Test that storage failure produces no false confirmation; use controlled test storage conditions rather than exposing failure-injection features publicly.
- Test tampered submissions, CSRF rejection, and escaped display of user-provided text. Verify database and private configuration cannot be downloaded over HTTP.
- Verify keyboard navigation, visible focus, labeled inputs, error announcements, accessible form opening/closing if a dialog is chosen, touch photo controls, and reduced-motion behavior.
- Perform visual checks at desktop, tablet, and mobile sizes, including narrow screens and long validation messages. Confirm there is no horizontal overflow and all three logos remain legible.

## Out of Scope

- Hosting selection, production deployment, DNS changes, and migration of the main Nyumba website.
- ID card or passport number collection, ID verification, and document uploads.
- Email, SMS, WhatsApp, or other outbound confirmation messages.
- Staff approval workflows, authentication, administrative dashboards, and Excel/CSV exports.
- Visitor accounts, public booking lookup, cancellation, rescheduling, and payments.
- Real capacity limits, accommodation inventory, blackout-date management, and guaranteed room allocation until operational rules are supplied.
- Newsletter subscriptions, invented testimonials/statistics, unrelated marketing sections, and a content-management system.
- Implementation tickets, application code, or production data creation as part of this specification task.

## Further Notes

- The repository currently contains only engineering-skill configuration; this is a greenfield application with no established architecture or test conventions.
- The user chose PHP and explicitly deferred hosting selection. Production hosting must support persistent writable SQLite storage and appropriate protection and backups.
- Operational schedules beyond Feeding Centre's 11 am slot are unresolved by design. Placeholder schedules are sufficient for initial development but should be reviewed before public launch.
- Final approved logo and photo files, destination descriptions, exact domain, and booking/contact copy remain content handoff items. Temporary development assets must be clearly marked and must not misrepresent the sites.
- Data retention, real slot capacities, and accommodation guarantees need owner decisions before those policies are implemented. No retention period or capacity limit has been agreed.
- The browser-level testing seam is user-approved. Confirmed product decisions above are synthesized from the conversation; proposed implementation defaults are identified separately.
