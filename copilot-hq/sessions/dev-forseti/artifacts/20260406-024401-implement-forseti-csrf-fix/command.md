# Implement: forseti-csrf-fix (P0 Security)

- Feature: forseti-csrf-fix
- Release: 20260406-forseti-release-b
- Priority: P0 — CSRF tokens missing from job_hunter routing
- Site: forseti.life

## Context

CSRF tokens are missing from POST routes in the `job_hunter` module. Using `_csrf_token: 'TRUE'` on GET+POST routes causes GET 403 regressions (known KB lesson). The fix requires the split-route pattern.

## Acceptance criteria

- All job_hunter POST routes (form submissions, queue actions, bulk operations) use `_csrf_token: 'TRUE'`
- All job_hunter GET routes do NOT use `_csrf_token: 'TRUE'`
- Split-route pattern applied: separate GET-only and POST-only route entries for paths that serve both
- Twig templates use `path()` which auto-appends `?token=` for CSRF-protected routes
- No GET 403 regressions on any job_hunter path

## Verification

```bash
# Check routes file
grep -n "_csrf_token" sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml

# Smoke test (must return 403 for auth, not 403 due to CSRF)
curl -s -o /dev/null -w "%{http_code}" https://forseti.life/jobhunter
# Expected: 403 (auth required) — NOT 403 from CSRF

# After fix, authenticated POST must succeed with token, fail without
```

## KB reference

- CSRF split-route pattern: org-wide CSRF routing memory: "Drupal _csrf_token: 'TRUE' on GET+POST routes causes GET 403 regressions. Use split-route pattern."

## Rollback

- Revert changes to `job_hunter.routing.yml` and any Twig templates modified

## Definition of done

- Commit hash provided
- All GET job_hunter routes return 403 (auth) not 403 (CSRF)
- All POST job_hunter routes validated with `_csrf_token: 'TRUE'`
- Agent: dev-forseti
- Status: pending
