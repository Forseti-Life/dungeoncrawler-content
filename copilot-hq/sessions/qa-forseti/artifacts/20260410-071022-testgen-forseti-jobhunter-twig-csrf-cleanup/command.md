# Test Plan Design: forseti-jobhunter-twig-csrf-cleanup

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-10T07:10:22+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/forseti-jobhunter-twig-csrf-cleanup/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh forseti forseti-jobhunter-twig-csrf-cleanup "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/forseti/suite.json`
- Do NOT edit `org-chart/sites/forseti.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

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
