# School projects and donations — ticket map

The owner approved these seven vertical slices and blocking edges on 5 October 2026. All implementation is based on `v3`. The [parent spec](spec.md) remains unchanged.

| Ticket | Blocked by | Status |
| --- | --- | --- |
| [01 — Show completed school projects](issues/01-school-project-showcase.md) | None | resolved |
| [02 — Submit school requests and review them in admin](issues/02-school-requests.md) | None | resolved |
| [03 — Deliver school-request email notifications reliably](issues/03-school-request-notifications.md) | 02 | resolved |
| [04 — Collect direct M-Pesa donations and record receipts](issues/04-direct-mpesa-donations.md) | None | resolved |
| [05 — Donate through a payment QR code](issues/05-donation-qr-payments.md) | 04 | resolved |
| [06 — Recover delayed payments and reconcile discrepancies](issues/06-payment-reconciliation.md) | 05 | resolved |
| [07 — Verify the complete experience and operational readiness](issues/07-operational-readiness.md) | 01, 03, 06 | needs-info |

Tickets **01–06 are resolved** with implementation and focused tests. Ticket **07 remains needs-info** for real launch inputs and sandbox evidence. No implementation ticket is currently unclaimed.

[Implementation and review evidence](../../docs/community-review.md) · [Operator guide](../../docs/community.md)

Each ticket includes its own tests through the real PHP application, isolated SQLite/session storage, and mocked external providers. Ticket 07 combines already-tested slices and verifies operational readiness; it is not a substitute for testing earlier tickets. No separate prefactoring ticket is needed: each slice keeps any necessary adaptation narrow and preserves existing behavior.

Owner-approved project content, a designated school notification recipient, and configured M-Pesa merchant/API access are launch inputs, not invented data. Implementation can proceed with clearly labelled fixtures and safe unavailable states. Sandbox evidence and any unavailable production-only capability must be reported accurately. Do not mark operational readiness complete while required evidence or launch inputs are missing.

On resolution, append the result to the ticket and update this map with its status, a concise summary, and a link to the evidence. Publishing these tickets does not deploy the feature, authorize live test payments, or change other branches.


## Implementation results

The showcase and request flow, durable staff notifications, direct receipt recording, QR attempts, and reconciliation are implemented on v3. Each resolved ticket links to its result and evidence. The independent standards and spec reviews have no remaining actionable local findings after fixes. Ticket 07 records the external readiness gap; its unresolved state does not imply live collection is enabled.
