# Feature Brief

- Work item id: forseti-jobhunter-twig-csrf-cleanup
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Release: 20260410-forseti-release-b
- Priority: P2
- Feature type: needs-testing
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Release target: 20260410-forseti-release-b

## Goal

Remove dead-code hidden CSRF token fields from three job_hunter Twig templates. `CsrfAccessCheck` reads only the URL query string `?token=` (appended by `RouteProcessorCsrf`); hidden `name="form_token"` and `name="token"` body inputs on `_csrf_token: 'TRUE'` routes are silently ignored. Leaving them misleads future developers into repeating the pattern.

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

### Coverage determination

Feature type: needs-testing — implementation complete in dev commit c0f597279. QA must verify absence of dead fields and confirm functional forms still work.

### Test path guidance for QA

- Grep verification: `grep -n 'name.*form_token\|name="token"' sites/forseti/web/modules/custom/job_hunter/templates/*.twig` must return 0 results
- Functional: POST to `/jobhunter/cover-letter/{id}`, `/jobhunter/interview-prep/{id}`, `/jobhunter/saved-searches` must still succeed (CSRF token still in URL query string)
