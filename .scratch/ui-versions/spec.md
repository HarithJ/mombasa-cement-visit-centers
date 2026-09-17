# Serve all three UI versions from main

Status: implemented

Preserve the designs from v1 (9021b48), v2 (67f45d7) and v3 (a8d4f81) at /v1, /v2 and /v3 respectively. Keep the existing root route on v1. Accept trailing slashes and explicit index.php paths. Share persistence, schedules, sessions and unchanged assets. Keep every booking action and confirmation within its selected UI version. Unknown routes return 404.

Implementation: explicit route allowlist in public/index.php, private PHP templates in src/views, and frontend snapshots in public/assets/versions. No original branch is modified.

Verification: tests/versions.test.mjs covers all three versions with real browsers and isolated storage, including no-JavaScript day bookings and JavaScript overnight bookings. Existing overnight backend regression suite also runs. PHP syntax and diff whitespace checks pass.
