# Implement: forseti-jobhunter-application-status-dashboard

- Release: 20260409-forseti-release-f
- Feature: `forseti-jobhunter-application-status-dashboard`
- Module: `job_hunter`
- AC file: `features/forseti-jobhunter-application-status-dashboard/01-acceptance-criteria.md`
- Test plan: `features/forseti-jobhunter-application-status-dashboard/03-test-plan.md`

## Summary
Add My Jobs pipeline view at `/jobhunter/my-jobs`. Groups job applications by `workflow_status`, adds status/company filters (GET params), bulk archive (POST + CSRF token), empty state, pagination.

No new data model — uses existing `workflow_status` field.

## Acceptance criteria (key ACs)
- AC-1: Pipeline stage display grouped by workflow_status with count badge
- AC-2: `?filter_status=` GET param, sanitized against known enum
- AC-3: Company dropdown filter (AND logic with status filter)
- AC-4: Bulk archive via POST + CSRF token; unknown/missing token → 403
- AC-5: Pagination (25 jobs/page), accessible
- AC-6: Empty state message when no jobs match

## Route decision
- New route: `GET /jobhunter/my-jobs` (authenticated only)
- POST for bulk archive: split route with `_csrf_token: 'TRUE'` on POST only (see CSRF split-route pattern in KB)

## Definition of done
- All ACs pass
- Commit hash provided with rollback note
- No PHP errors in `drush watchdog:show`

## Verification
```bash
curl -s https://forseti.life/jobhunter/my-jobs  # 403 anonymous
# Log in as test user, verify pipeline groups render
```
