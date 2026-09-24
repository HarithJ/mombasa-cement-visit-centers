# Admin implementation review

Baseline: `c205517` (before admin work). Initial implementation: `1d85b7c`; review fixes and expanded regression coverage: `7a7b44c`.

## Standards

No documented repository-standard violations or additional actionable security findings were identified. The reviewer found one correctness issue: PHP treated the valid search string `0` as empty. The implementation now checks for an empty string explicitly, and a deterministic browser regression covers the case.

Initial findings: one correctness issue. Remaining findings: none.

## Spec

The reviewer found the same zero-search issue and missing explicit proof that admin reads and a populated schema upgrade preserve existing records. Both are resolved: the integration test upgrades a populated pre-admin database and compares bookings, feedback responses, invitations, and queued emails before and after the admin workflow. A follow-up spec review confirmed both fixes. No scope creep was identified.

Initial findings: two. Remaining findings: none.

## Verification

The real PHP/browser suite exercises login, logout, session regeneration, idle/absolute expiry, credential rotation, persistent throttling across cookie resets, CSRF rejection, protected URLs, filtering/pagination, detail navigation, day and overnight data, feedback states, escaping, legacy missing email, populated migrations, and public/admin session separation. It uses isolated temporary storage, a server-side controlled test clock, and no real email sends.

Desktop and mobile screenshots were inspected. A long booking reference originally overflowed the mobile heading; it now wraps, with a viewport overflow assertion guarding the fix. Core workflows work without JavaScript and with JavaScript enabled.

The shared implementation is applied to main, v1, v2, v3, and design-preview source. Static exports use the existing asset allowlist and exclude administration. Operator configuration is documented in the admin guide. Live credentials and deployment are not part of this change.

The full regression run passed admin, email queue, feedback worker/migration, contacts, versions, feedback, and static-preview suites. Two older booking suites still selected the previously removed destination dropdown; their tests were corrected to reopen through destination buttons and both passed targeted reruns. Admin integration tests also passed individually in main, v1, v2, and v3. PHP syntax checks, Node syntax checks, and diff whitespace checks passed.
