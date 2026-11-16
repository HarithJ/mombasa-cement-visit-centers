# 06 — Users page and welcome email

Type: feature
Blocked by: 01, 02
Status: ready-for-agent

**What to build:** Admin-only user management in `/admin`, and the welcome email, per the spec's [Users page](../spec.md#users-page-admins-only) and [wording.md](../wording.md) §11 footer note, §12 welcome email and §13.

- [ ] `/admin/users` (admins only; team users get 403/redirect and no nav item): list, add, edit, deactivate, reactivate via ticket 01's module, with the actor-aware guards (no self-deactivation, last active admin).
- [ ] Adding a user queues the welcome email with a 48-hour single-use sign-in link (ticket 02's token helper). `bin/users.php add` without `--no-welcome` now sends it too.
- [ ] Deactivation or email change ends that user's sessions and invalidates their links (credential counter).
- [ ] Sign-in page footer note uses the approved wording.
- [ ] Tests: admin-only access, each action, guards, welcome email contents and link behaviour, CLI welcome.
