# Feature Brief

- Work item id: forseti-jobhunter-twig-csrf-cleanup
- Website: forseti.life
- Module: job_hunter
- Project: PROJ-004
- Group Order: 6
- Group: infrastructure
- Group Title: Technical Infrastructure
- Group Sort: 7
- Status: shipped
- Release: 20260410-forseti-release-b
- Priority: P2
- Feature type: needs-testing
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Release target: 20260410-forseti-release-b

## Goal

Remove ALL dead-code hidden CSRF token fields from job_hunter Twig templates. `CsrfAccessCheck` reads only the URL query string `?token=` (appended by `RouteProcessorCsrf`); hidden `name="form_token"` and `name="token"` body inputs on `_csrf_token: 'TRUE'` routes are silently ignored. Leaving them misleads future developers into repeating the pattern.

**PM scope decision (2026-04-10):** QA unit-test audit found 3 additional confirmed dead fields beyond the original 3 templates. Scope expanded to clean all dead CSRF fields across all job_hunter templates in one pass.

## Non-goals

- No functional change to CSRF validation logic
- No changes to routing or controller code
- Not cleaning up other modules (scope: job_hunter templates only)

## Gap Analysis

### Implementation status

| Requirement | Existing code path | Coverage status |
|---|---|---|
| Remove dead `name="form_token"` from cover-letter-display.html.twig | `job_hunter/templates/cover-letter-display.html.twig` | Full — dev commit c0f597279 |
| Remove dead `name="form_token"` from interview-prep-page.html.twig | `job_hunter/templates/interview-prep-page.html.twig` | Full — dev commit c0f597279 |
| Remove dead `name="token"` from saved-searches-page.html.twig | `job_hunter/templates/saved-searches-page.html.twig` | Full — dev commit c0f597279 |
| Remove dead `name="form_token"` at lines 41+190 from google-jobs-search.html.twig | `job_hunter/templates/google-jobs-search.html.twig` | Pending — QA BLOCK issued |
| Remove dead `name="token"` at line 309 from job-tailoring-combined.html.twig | `job_hunter/templates/job-tailoring-combined.html.twig` | Pending — QA BLOCK issued |

### Coverage determination

Feature type: needs-testing — partial implementation in dev commit c0f597279. QA BLOCK issued for 3 remaining dead fields in 2 templates. Dev-forseti must complete cleanup; then QA re-verifies.

### Test path guidance for QA

- Grep verification: `grep -n 'name.*form_token\|name="token"' sites/forseti/web/modules/custom/job_hunter/templates/*.twig` must return 0 results
- Functional: POST to `/jobhunter/cover-letter/{id}`, `/jobhunter/interview-prep/{id}`, `/jobhunter/saved-searches` must still succeed (CSRF token still in URL query string)

## Security acceptance criteria

- Authentication/permission surface: All job_hunter routes affected are authenticated-user-only; no anonymous access to these form pages.
- CSRF expectations: CSRF is enforced via URL query token (`?token=`) appended by `RouteProcessorCsrf`. This change REMOVES misleading POST-body hidden fields that were never read by `CsrfAccessCheck`. The URL-based CSRF enforcement is unchanged and remains active.
- Input validation: No input validation changes — this is a template-only cleanup removing dead hidden fields. No new user-controlled input is introduced.
- PII/logging constraints: No PII is introduced or removed. No logging changes. Templates render existing data only.
