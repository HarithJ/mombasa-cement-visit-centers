# Ticket 03 requirement audit

Final result: seven overnight and ten day browser groups pass. Reviewed narrow validation and desktop saved confirmation; a separate close toolbar prevents overlap with scrollable fields, verified at all four widths. Ticket 03 resolved.

| Requirement | Authoritative evidence |
| --- | --- |
| Approved controls create real stays | Galana checkbox/conditional fields submit through existing PHP POST and prepared transactional write; single-night browser test inspects saved dates/guests and confirmation. |
| Arrival/date/guest rules without invented limits | Server validator requires matching valid visit/arrival, real departure after arrival, integer guests 1–attendees. Single-night and 45-night stays pass. Eleven tampered/missing/invalid combinations save nothing. |
| Accessible errors and retained entries | PHP field messages plus JS aria-invalid/error focus; checkbox error has aria-describedby. Invalid tests retain name and checked stay, corrected submission saves once. Derived arrival resets to visit date for correction. |
| Nondestructive persistence | Migration 002 adds flag/nullable fields and insert/update consistency triggers. A v1 day fixture survives migration twice with original reference/name/time and null stay details. |
| Disabled/stale details excluded | UI hides/clears disabled stay fields and destination changes. Server normalizes unchecked/non-Galana requests to flag=0 and null details even when malicious stale fields are posted. Tests verify all three destinations. |
| Saved summary, no allocated-room claim | PHP and JS confirmation include saved date range/staying guests. Copy explicitly describes stays as requests, not room allocations. No capacity/pricing/inventory implementation. |
| Existing security and day behavior | Full day suite covers CSRF, escaping, private files, failure/retry, replay/refresh/restart, prepared persistence, masked phones, schedules/migrations and real day visits. Overnight replay/refresh retains one request. |
| Real-app browser coverage | Isolated PHP/SQLite/session browser suite covers single/multi-night, eleven invalid cases, corrections, toggling/destination changes, tampering, saved details, no-JavaScript fallback and responsive screenshots. |
| Invalid creates zero; correction one | Record count remains unchanged across invalid requests and increases exactly once after correction. Responsive cases repeat this for missing departure. |

Overnight form/validation/confirmation screenshots are generated under ignored `test-results/` at 320/390/768/1440px. All tests use synthetic visitors and isolated storage. Hosting, room inventory, pricing, capacity allocation and approval remain out of scope. Release hardening is ticket 04.
