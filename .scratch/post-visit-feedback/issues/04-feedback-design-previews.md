# 04 — Interactive feedback design previews

**What to build:** Reviewers can explore sample day-visit and overnight feedback journeys in each static design, including ratings, non-attendance, validation and acknowledgement, without making a real submission or sending an email.

**Blocked by:** 01 — Day-visit feedback from email to saved response; 02 — Feedback after Galana overnight stays

**Status:** resolved

**Type:** feature

- [ ] Provide discoverable sample feedback journeys for v1, v2 and v3, matching each design and showing fictional destination/date context, including a Galana overnight sample.
- [ ] Clearly label each journey as a design preview. Completion must say that no feedback was saved; it must not imitate a real submitted response or claim that an invitation was sent.
- [ ] Allow ratings, optional comments and “I didn’t attend,” with validation and stale-rating clearing consistent with the PHP experience. Keep the real feature’s backend validation authoritative.
- [ ] Do not issue submission or email API requests, read real bookings, export real invitation tokens, include credentials, or persist entered personal data. Reloading resets the sample.
- [ ] Work at the site root and under the GitHub Pages repository subpath. Keep current booking previews and navigation working.
- [ ] Check mobile, tablet and desktop layouts, keyboard controls, labels and error feedback. With JavaScript disabled, show an honest preview limitation and prevent a fake submission.
- [ ] Browser tests exercise sample attended and non-attended flows and confirm no data-bearing network requests or backend files are exported.
- [ ] Commit the preview work on design-preview. Any shared production UI changes must also be committed on main and the affected actual version branches. No hosting change, push or production deployment is required.
- [ ] This ticket depends on the day and overnight user journeys, but not on ticket 03’s operational delivery work; the preview never calls the worker.

## Answer

Exported day and overnight sample feedback pages for all three designs, linked from the preview toolbar. Browser checks pass at root and repository subpaths, across responsive widths, with no submission or data persistence and honest no-JavaScript behaviour.
