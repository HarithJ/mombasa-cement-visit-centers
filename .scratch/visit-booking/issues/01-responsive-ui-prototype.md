# 01 — Build the modern, professional responsive UI prototype

**What to build:** A polished visitor-facing prototype where visitors explore Sahajanand School, Galana, and the Feeding Centre, open a preselected booking form, enter day or overnight details, and preview confirmation without saving a booking. This is the explicitly approved UI-only prototype exception to vertical slicing. Present it for owner review before backend integration.

**Blocked by:** None — can start immediately.

**Status:** resolved

- [x] Create a spacious, contemporary, professional composition with clear hierarchy, generous whitespace, consistent spacing, modern sans-serif typography, white/charcoal/soft-gray surfaces, and one brand-aligned accent. Avoid a generic template appearance, clutter, excessive shadows, and flashy effects.
- [x] Arrange Nyumba Group, Nyumba Foundation, and Nyumba Agri branding clearly across the top without header links (owner revision), with a concise headline/supporting statement that orient visitors toward booking.
- [x] Display three large photography-led destination columns on desktop, each with its destination name, concise description, and clearly visible Book visit button. Adapt naturally to tablet and mobile without horizontal overflow.
- [x] Use owner-approved logos and photographs when supplied. Clearly identify development placeholders; do not fabricate documentary images, destination facts, testimonials, or impact statistics.
- [x] Change destination photographs through smooth, restrained hover and keyboard-focus transitions, with a labeled alternate-photo control usable on touch devices. Respect reduced-motion preferences.
- [x] Each Book visit button displays a clean form with that destination preselected. Provide full name, phone number, destination dropdown, visit date, destination-specific time slot, and positive attendee count. Include Feeding Centre's 11 am slot and identify other schedules as provisional.
- [x] Provide a Galana-only overnight checkbox and conditional arrival date, departure date, and overnight guest count controls. Hide irrelevant controls when selecting another destination or disabling overnight.
- [x] Demonstrate readable field-level validation, retained input, submission/loading, failure, and mock confirmation states. Include a sample reference and summary; clearly state that this is a prototype and no booking is saved or sent.
- [x] Do not collect ID numbers or email addresses, send notifications, or persist personal details in a database or browser storage. Use synthetic data for review and tests.
- [x] Provide visible keyboard focus, semantic labels, descriptive image text, strong contrast, comfortable touch targets, and announced validation messages. If using a dialog, implement focus management, Escape dismissal, and focus restoration.
- [x] Verify the prototype in browser-level interaction tests and visually review desktop, tablet, and narrow mobile layouts, including validation and confirmation states.
- [x] Keep implementation compatible with the agreed PHP/server-rendered HTML, CSS, and minimal-JavaScript approach; do not introduce Next.js, Django, or a separate frontend service.
- [x] Present the prototype to the owner and record explicit visual approval or requested changes. Backend integration must not begin until the owner approves the UI.

## Scope notes

Use the agreed visitor-booking specification as product context. This ticket produces no real bookings, server-side booking validation, or SQLite persistence. Asset handoff need not block a clearly labeled development prototype; final authentic imagery remains a launch prerequisite.

## Comments

Latest audit: Nyumba Agri enlarged to 150px desktop, 104px mobile, 90px narrow mobile. Header-link removal supersedes the original navigation requirement. Current backend approval is still pending; later Galana photography supersedes the initial placeholder instruction.

Owner revision: removed all header links and its booking CTA. Distributed the three non-linked logos across equal-width columns, retaining a single spaced row on mobile.

Owner requested a more composed header. Balanced logo sizes and spacing, aligned header with the content grid, changed the CTA to a quieter soft-green treatment, and stacked Foundation/Agri alongside the Group mark on mobile for readability.

Galana photo handoff received: fourteen supplied images optimized into WebP copies, with crop rows and sunset irrigation leading the gallery. Removed the destination placeholder through normal asset discovery; hover cycling and the destination-circle progress ring now apply to all three galleries. Originals unchanged.

Owner clarified the indicator belongs on the destination-number circle. Removed the separate count and added a thin timed progress ring around 01/03. The ring fills over the 2.5-second hover interval, resets for each photo, and stops with cycling; no animation for reduced motion or the Galana placeholder.

Owner requested a visual gallery indicator. Added an understated current/total photo counter, synchronized with hover and manual advancement. The Galana placeholder has no misleading photo count.

Owner revision: use more photographs and keep changing while the pointer remains over a panel. Implemented seven School and fourteen Feeding Centre images with 2.5-second hover cycling, stopping on leave, manual next-photo controls and reduced-motion suppression. Original assets remain unchanged; Galana still uses its labeled placeholder.

### 15 September 2026 — Implementation progress

The PHP-rendered UI prototype is running locally, with official Nyumba logos, responsive destination panels, accessible booking dialog, day/overnight validation, synthetic loading/failure/confirmation previews, and no persistence. Sixteen grouped browser checks passed, including 320/390/768/1440-pixel layouts and touch controls. Visual QA and test coverage are recorded in the UI review document.

The requested Sahajanand School and Feeding Centre photo assets are not accessible yet; an attachment/folder-path request has been sent. Labeled illustrations are temporary for those destinations and intentional for Galana. Owner visual approval remains pending. Keep this ticket open and do not start backend integration.

Follow-up audit: loading now locks sample-data controls, repeatable hover and keyboard photograph reveal have been verified, and photo/close controls use 44-pixel touch targets. Seventeen grouped browser checks pass. A requirement-by-requirement audit records the remaining photo handoff and owner approval gates; the implementation remains incomplete until those are resolved.

Photo handoff resolved: found eight School and fourteen Feeding Centre source images. Integrated an entrance/courtyard pair and prepared-meals/meal-service pair as optimized WebP copies, with descriptive alt text and unchanged originals. Galana intentionally retains its labeled placeholder. Owner visual approval remains the only release gate for this UI ticket; backend work has not started.

## Answer

Owner explicitly approved the current UI on 15 September 2026 (“I approve”). Implemented and visually reviewed the PHP-rendered responsive prototype, supplied photography for all three destinations, continuous hover galleries with circle progress indicators, logo-only header, and accessible day/overnight booking previews. All 17 grouped browser checks pass, including the latest header assertions. No persistence, notifications, or backend implementation was introduced. Approval gate satisfied.
