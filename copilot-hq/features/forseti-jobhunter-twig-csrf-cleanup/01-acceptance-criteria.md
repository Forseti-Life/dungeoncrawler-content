# Acceptance Criteria (PM-owned)

## Gap analysis reference

Gap Analysis in `feature.md` is complete. All three template changes are `TEST-ONLY` — implementation exists in dev commit c0f597279.

## Happy Path
- [ ] `[TEST-ONLY]` `grep -n 'name.*form_token\|name="token"' sites/forseti/web/modules/custom/job_hunter/templates/*.twig` returns 0 results (dead fields removed).
- [ ] `[TEST-ONLY]` POST to `/jobhunter/cover-letter/{id}` succeeds with valid CSRF token in URL query string (functional form still works).
- [ ] `[TEST-ONLY]` POST to `/jobhunter/interview-prep/{id}` succeeds with valid CSRF token in URL query string.
- [ ] `[TEST-ONLY]` POST to `/jobhunter/saved-searches` succeeds with valid CSRF token in URL query string.

## Edge Cases
- [ ] `[TEST-ONLY]` No additional hidden input fields with token-like names (`form_token`, `token`, `csrf`) remain in any job_hunter template.

## Failure Modes
- [ ] `[TEST-ONLY]` POST without a valid `?token=` query parameter is rejected with 403 (CSRF validation still enforced via URL token).

## Permissions / Access Control
- [ ] Authenticated user behavior: form submission succeeds for authenticated users.
- [ ] Anonymous user behavior: form pages not accessible to anonymous (existing auth gates unchanged).

## Data Integrity
- [ ] No data loss: change is template-only; no migration needed.
- [ ] Rollback path: revert commit c0f597279 removes dead fields re-addition; no functional impact.

## Knowledgebase check
- Related lessons: `knowledgebase/lessons/` — CSRF split-route pattern (Drupal `_csrf_token: 'TRUE'` on GET+POST routes causes GET 403; use split-route pattern; `RouteProcessorCsrf` appends `?token=` for Twig `path()`).
