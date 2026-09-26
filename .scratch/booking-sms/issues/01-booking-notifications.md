# 01 — Send visitor and destination-manager booking SMS

Status: resolved
Type: feature
Blocked by: None

- [x] Implement the eight approved scenario/audience messages and destination routing.
- [x] Reuse phone normalization and retain ambiguous booking numbers without guessing a country code.
- [x] Save SMS jobs atomically with new bookings; prevent duplicates and backfills.
- [x] Add worker claims, bounded retries, uncertain-outcome review, and acceptance tracking.
- [x] Keep local/provider configuration problems outside retry attempts and document pause recovery.
- [x] Verify with mocked transport, migration, configuration, concurrency, and recovery tests.
- [x] Complete full regression verification.

## Answer

Implemented on design-preview, main, v1, v2, and v3. PHP syntax checks, client/worker tests, and the full npm test suite pass. All SMS tests use fake HTTP transport. Sending is disabled by default; no real SMS was sent and no production scheduler or credentials were changed. See the [map](../map.md).
