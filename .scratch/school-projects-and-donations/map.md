# School projects and donations — ticket map

The owner approved these seven vertical slices and blocking edges on 5 October 2026. All implementation is based on `v3`. The [parent spec](spec.md) remains unchanged.

| Ticket | Blocked by | Status |
| --- | --- | --- |
| [01 — Show completed school projects](issues/01-school-project-showcase.md) | None | ready-for-agent |
| [02 — Submit school requests and review them in admin](issues/02-school-requests.md) | None | ready-for-agent |
| [03 — Deliver school-request email notifications reliably](issues/03-school-request-notifications.md) | 02 | ready-for-agent |
| [04 — Collect direct M-Pesa donations and record receipts](issues/04-direct-mpesa-donations.md) | None | ready-for-agent |
| [05 — Donate through a payment QR code](issues/05-donation-qr-payments.md) | 04 | ready-for-agent |
| [06 — Recover delayed payments and reconcile discrepancies](issues/06-payment-reconciliation.md) | 05 | ready-for-agent |
| [07 — Verify the complete experience and operational readiness](issues/07-operational-readiness.md) | 01, 03, 06 | ready-for-agent |

The initial frontier is **01, 02, and 04**. Select open, unblocked, unclaimed tickets in numerical order; claim before implementation. Ticket 06 inherits 04 through 05; ticket 07 inherits 02, 04, and 05 through its immediate blockers. No redundant blocking edges are necessary.

Each ticket includes its own tests through the real PHP application, isolated SQLite/session storage, and mocked external providers. Ticket 07 combines already-tested slices and verifies operational readiness; it is not a substitute for testing earlier tickets. No separate prefactoring ticket is needed: each slice keeps any necessary adaptation narrow and preserves existing behavior.

Owner-approved project content, a designated school notification recipient, and configured M-Pesa merchant/API access are launch inputs, not invented data. Implementation can proceed with clearly labelled fixtures and safe unavailable states. Sandbox evidence and any unavailable production-only capability must be reported accurately. Do not mark operational readiness complete while required evidence or launch inputs are missing.

On resolution, append the result to the ticket and update this map with its status, a concise summary, and a link to the evidence. Publishing these tickets does not deploy the feature, authorize live test payments, or change other branches.
