# 06 — Recover delayed payments and reconcile discrepancies

**What to build:** Delayed or missing payment outcomes can be recovered through provider checks or verified merchant evidence, with traceable admin history and accurate totals. Recovery never requests another payment.

**Blocked by:** 05

**Status:** claimed

**Type:** feature

**Base branch:** v3

- [ ] Schedule bounded provider-status checks for unresolved transactions with supported receipt/originator identifiers and configured API access. Use leases, network timeouts, backoff, and durable retry state; process asynchronous results idempotently.
- [ ] Missing callbacks, abandoned pages, and expired local waiting periods remain unknown/review outcomes rather than proof of payment failure. Confirmed late receipts update the linked donor status and admin record without initiating another payment.
- [ ] Validate provider query results and asynchronous responses against merchant, environment, identifiers, and amount. Preserve earlier verified facts when later evidence conflicts; surface discrepancies for review.
- [ ] Provide a restricted operator reconciliation path to recover a missed receipt from provider-verified transaction data or verified merchant statement evidence. Record evidence, actor/source, timestamp, and adjustment reason. Do not accept donor-supplied receipt text as proof or expose public manual confirmation.
- [ ] Make repeated reconciliation idempotent using the same receipt identity as callbacks. A recovered transaction links to an attempt only through reliable references, and an unresolved attribution remains excluded from donation totals.
- [ ] Preserve evidenced reversals as linked adjustments without deleting original receipts. Update net totals consistently and retain the original payment history; this does not initiate a provider reversal or refund.
- [ ] Expose unresolved, retrying, reconciled, conflicting, and reversed outcomes with concise history in admin. Show any donor-facing updates only through the existing private status access.
- [ ] Handle missing provider identifiers explicitly: the status API cannot discover arbitrary unknown transactions. Document merchant-statement comparison and support escalation for discovery gaps instead of claiming complete automatic recovery.
- [ ] Verify recovery through real application endpoints/worker entry points, a controlled clock, and mocked provider responses. Cover timeout/retry, concurrent processing, late/out-of-order responses, conflicting evidence, missed-receipt recovery, repeated recovery, attribution failure, and reversal totals.
- [ ] Confirm callbacks and reconciliation racing on the same receipt create one transaction, one accounting effect, and traceable history. Assert recovery does not call payment-initiation operations.
- [ ] Document schedules, credentials/roles, monitoring, evidence handling, recovery procedures, and known provider limitations. Protect reconciliation tooling and private payloads; do not introduce a public override or store secrets in logs.
