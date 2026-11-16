# Visit approval and staff accounts — ticket map

| Ticket | Blocked by | Status |
| --- | --- | --- |
| [01 — User accounts table and `bin/users.php`](issues/01-user-accounts-and-cli.md) | None | resolved |
| [02 — Sign-in with accounts, login links and password reset](issues/02-sign-in.md) | 01 | resolved |
| [03 — Pending bookings and approval-request messages](issues/03-pending-bookings-and-approval-requests.md) | 01 | resolved |
| [04 — Decide from the email buttons, and decision messages](issues/04-decide-from-email.md) | 03 | resolved |
| [05 — Decide and change decisions in the admin panel](issues/05-admin-decisions.md) | 02, 04 | resolved |
| [06 — Users page and welcome email](issues/06-users-page-and-welcome.md) | 01, 02 | resolved |

[Parent spec](spec.md) · [Approved wording](wording.md)

Integration branch: `visit-approval` (from `galana-3d`). After completion, port to `galana-only`. Migration numbers are reserved per ticket: 019 (01), 020 (02), 021 (03).

## Resolution

All six tickets resolved on `visit-approval` (from `galana-3d`), followed by code-review fixes in `2d2d44b`. Full suite (`npm test`) passes. Not pushed, not deployed, not yet ported to `galana-only`.
