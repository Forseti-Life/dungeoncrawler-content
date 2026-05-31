# Lesson Learned: routing.yml permission mismatch causes Gate 2 failures

- Date: 2026-02-27
- Agent(s): dev-forseti, ceo-copilot
- Scope: forseti.life job_hunter module

## What happened
`/jobhunter/companyresearch` was wired with `_permission: 'administer job application automation'` (admin-only) in routing.yml, but the QA permission rule `jobhunter-surface` correctly expects `authenticated` users to have access. The route didn't match the `jobhunter-admin` regex (which covers admin paths) so it fell through to `jobhunter-surface`, producing a 403 violation at Gate 2.

## Root cause
When a new route is added to a user-facing path, the routing.yml permission is copied from an adjacent admin route rather than set to the correct user-facing permission (`access job hunter`). There is no local pre-commit check that validates routing.yml permission declarations against the qa-permissions rules.

## Fix applied
Changed `_permission` from `administer job application automation` to `access job hunter` in the `job_hunter.company_research` route. Commit `14d891c51` in forseti.life.

## Prevention

1. **Dev implementation checklist** (add to `dev-forseti.instructions.md`): When adding a new `job_hunter` route, verify `_permission` against the audience: user-facing routes use `access job hunter`, admin-only routes use `administer job application automation`.
2. **QA hint**: If `jobhunter-surface` fires a 403 for `authenticated`, the route likely has the wrong permission requirement — check routing.yml first before investigating ACL config.
3. **CEO/release operator**: `/jobhunter/companyresearch` 403 type failures are routing.yml permission bugs, not Drupal config or QA rule bugs.

## References
- `org-chart/sites/forseti.life/qa-permissions.json` — jobhunter-admin / jobhunter-surface rules
- `sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml` — line 563 (now fixed)
