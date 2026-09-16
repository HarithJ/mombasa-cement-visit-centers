# UI prototype review

Owner approved the current UI on 15 September 2026 (“I approve”). UI ticket 01 is resolved. The visual approval gate is satisfied; backend implementation remains separate and has not started.

Latest asset update: all three destinations now use supplied photography—seven school, fourteen Feeding Centre, and fourteen Galana images. Galana no longer displays the illustration. Each gallery has continuous hover cycling and a timed progress ring around its destination number; the separate photo counter has been removed. Earlier placeholder notes below are historical.

## Design direction

Warm white, charcoal, and Nyumba green; typography-led introduction above three generous photography panels. Authentic destination images provide the primary visual anchor. Keep copy short, actions clear, and spacing consistent. Motion consists of a gentle introduction, scroll reveals, photograph crossfades, and a restrained dialog entrance.

## QA inventory

Use synthetic details only. Check external behavior through normal browser controls and inspect visuals separately.

| Surface / claim | Functional check | Visual states |
| --- | --- | --- |
| Three brands, clear navigation | Home and section anchors; official outbound links | Initial desktop, tablet, mobile header |
| Three photography-led destinations | All Book visit buttons preselect correct location | Initial page, destination panels, footer |
| Hover/focus/touch photo changes | Hover, keyboard focus, alternate/original toggle cycle | Primary, transition, alternate photo |
| Clean booking form | Required fields, dates, slots, attendee count | Empty desktop/mobile form |
| Destination switching | Slots update; Feeding Centre shows 11 am | Destination dropdown / selected slot |
| Galana overnight | Enable/disable; arrival tracks visit; guest/date validation | Expanded desktop/mobile form |
| Readable validation | Empty, past date, invalid phone/count; retain input | Errors and longest field messages |
| Loading and failure | Submit; simulated failure; correction and retry | Busy form, failure summary |
| Confirmation / edit / close | Masked phone; sample reference; edit retains; close discards | Day and overnight confirmation |
| Keyboard / dialog access | Initial focus, tab containment, Escape, focus restoration | Focus states and scrollable dialog |
| Responsive / reduced motion | No horizontal overflow at 320, 390, 768, 1440 pixels | All dense states and reduced-motion endpoints |
| No real bookings / privacy | No POST, cookies, local/session storage; close clears | Prototype disclaimers in form and confirmation |

Exploratory scenarios: close while the loading preview is active and immediately reopen another destination; switch away from Galana after entering overnight details; submit invalid dates/guest counts then correct them; show a failure then retry with confirmation selected.

## Owner review

Visual approval is pending. Backend integration must not start until the owner has reviewed and approved the prototype. Approved photography for Sahajanand School and Feeding Centre is required for the requested final composition; Galana remains an explicit illustrated placeholder.

## Verification evidence — 15 September 2026

- PHP lint, JavaScript syntax checks, and whitespace checks passed.
- The browser suite passed 17 grouped checks against the running PHP-rendered prototype, using actual control interactions and synthetic data. Coverage includes every destination, day/overnight preview, invalid input, preserved input, failure/retry, loading cancellation, dialog focus restoration, keyboard photograph reveal, repeatable hover after the alternate/original toggle cycle, real touch emulation, 44-pixel photo/close touch targets, reduced motion, and 320/390/768/1440-pixel viewport fit. Sample controls are locked while loading.
- Reviewed initial desktop/tablet/mobile screenshots, destination panels, planning section, footer, validation/failure states, and day/overnight confirmation. Corrected desktop first-screen action fit and ensured the close control remains visible while long dialog content scrolls.
- No horizontal overflow, broken image/font requests, browser runtime errors, submission requests, cookies, or browser-storage writes were detected in tested flows.
- Screenshot artifacts are produced by the test suite in the ignored test-results directory. Tests use the available compatible Chromium executable; the reproducible setup is documented in the README.
- Supplied photography is integrated: seven Sahajanand and fourteen Feeding Centre photos, with the entrance and prepared meals as initial views. Hover cycles through the gallery every 2.5 seconds and stops on leave. Touch/keyboard users have a manual next-photo button; reduced-motion users receive no automatic cycling. Optimized copies are served; original files remain untouched. Only Galana retains a labeled illustration.
- Owner visual approval has not been received. No backend integration has started.

## Requirement-by-requirement completion audit

| UI ticket requirement | Current evidence | Result |
| --- | --- | --- |
| Modern professional composition | Reviewed desktop, tablet, mobile screenshots; spacing, hierarchy and coherent branding implemented | Implemented; subjective owner approval still pending |
| Three logos and navigation | Official assets load; header and anchors inspected | Verified |
| Three large photography-led destination columns | Supplied school and Feeding Centre photography integrated in responsive panels | Implemented |
| Approved assets and honest placeholders | Three official logos and twenty-one supplied photos used; only Galana illustration labeled | Implemented |
| Hover, keyboard, touch and reduced-motion imagery | Real control tests passed, including repeatable hover and keyboard focus screenshot | Verified prototype behavior; recheck crops with real assets |
| Destination-prefilled form and all day fields | Three destination day flows and Feeding Centre 11 am tested | Verified |
| Conditional Galana overnight controls | Enable/disable, date validation, guest validation and destination reset tested | Verified |
| Validation, input retention, loading, failure and confirmation | Browser flows and screenshots reviewed; failure retries and loading lock tested | Verified |
| No ID/email collection, transmission or persistence | Form/control inspection and browser network/storage assertions | Verified |
| Keyboard, labels, contrast and touch usability | Native dialog focus, Escape/restoration, labels, visual focus and touch targets inspected | Verified prototype baseline; final image descriptions remain dependent on assets |
| Browser and responsive visual QA | 17 grouped tests and reviewed 320/390/768/1440 screenshots | Rechecked after photo integration |
| PHP-rendered HTML, CSS and minimal JavaScript | Runtime responds; PHP/JS lint passes; no alternative production framework | Verified |
| Owner visual approval before backend | No approval message received; no backend implementation present | Incomplete: owner review gate |

Do not close the UI ticket or mark the active implementation goal complete until the supplied photo assets are integrated, rechecked, and owner approval is recorded. Do not substitute stock/generated imagery for the user's supplied destination photographs.
