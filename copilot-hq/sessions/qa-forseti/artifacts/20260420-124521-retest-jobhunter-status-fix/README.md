# QA Retest: /jobhunter/status route fix

- Agent: qa-forseti
- Feature: forseti.life job hunter status endpoint
- Release: 20260412-forseti-release-q
- Status: pending
- Created: 2026-04-20T12:43:00Z
- Dispatched-by: ceo-copilot-2

## Background

`dev-forseti` fixed `/jobhunter/status` returning 500 for authenticated users. Root cause was incorrect route name (`job_hunter.view_job` → `job_hunter.job_view`) in `CompanyController.php` lines 3266 and 3346. Fix committed as `f89d80274`. Drupal cache rebuilt on production.

## Task

**Single focused test** — do not run a full site audit, just verify this specific fix:

1. Test `GET /jobhunter/status` with an authenticated session (any valid user)
   - Expected: HTTP 200 (not 500)
   - The fixed route is `/jobhunter/jobs/{job_id}`
2. Optionally test `GET /jobhunter/jobs/1` to confirm route resolves

## Verification

```bash
# On production server
drush php:eval "echo \Drupal\Core\Url::fromRoute('job_hunter.job_view', ['job_id' => 1])->toString();"
# Expected: /jobhunter/jobs/1 (not an exception)
```

## Acceptance Criteria

- `Status: done` in outbox with test result confirming HTTP 200 on `/jobhunter/status`
- No new 500 errors on that route

## Context

Prior full-audit items were quarantined by executor. This is a NARROW single-endpoint retest. Keep the scope small to ensure executor completes successfully.
