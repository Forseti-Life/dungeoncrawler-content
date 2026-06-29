# Test Plan Design: forseti-jobhunter-controller-refactor

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-05T21:07:14+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/forseti-jobhunter-controller-refactor/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh forseti forseti-jobhunter-controller-refactor "<brief summary>"
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
# Feature: forseti-jobhunter-controller-refactor (Phase 1)

## Gap analysis reference

Gap analysis in `feature.md` is complete. Phase 1 scope: DB query extraction only. Criteria are `[NEW]` for repository class and `[EXTEND]` for controller delegation.

## Happy Path

- [ ] `[NEW]` `JobApplicationRepository` class exists at `web/modules/custom/job_hunter/src/Repository/JobApplicationRepository.php`.
- [ ] `[NEW]` All 54 direct DB queries previously inline in `JobApplicationController` are now in `JobApplicationRepository`.
- [ ] `[EXTEND]` `JobApplicationController` no longer contains direct `\Drupal::database()` or `db_*` calls — it delegates to `JobApplicationRepository`.
- [ ] `[EXTEND]` All existing job application routes respond correctly (create, list, update status, delete).

## Edge Cases

- [ ] `[TEST-ONLY]` Paginated job application list returns correct results before and after refactor.
- [ ] `[TEST-ONLY]` Job status update is persisted correctly via repository.

## Failure Modes

- [ ] `[TEST-ONLY]` `JobApplicationRepository` throws a typed exception on DB connection failure (not silently swallowed).

## Permissions / Access Control

- [ ] `[TEST-ONLY]` No ACL changes — repository is internal; same permissions apply via controller routing.
- [ ] `[TEST-ONLY]` Anonymous users cannot access job application routes (403 expected; unchanged from pre-refactor).

## Data Integrity

- [ ] No data migration required — repository queries same tables.
- [ ] Rollback path: revert repository extraction commit; controller inline queries restored.

## Knowledgebase check

- Reference: `knowledgebase/lessons/20260301-jobhunter-routing-csrf-token-blocks-qa-probe.md` — CSRF probe pattern; QA must use authenticated session when testing controller routes.

## Verification method

```bash
# After refactor:
grep -rn "\\\\Drupal::database()" web/modules/custom/job_hunter/src/Controller/JobApplicationController.php
# Expected: 0 matches

grep -rn "JobApplicationRepository" web/modules/custom/job_hunter/src/Controller/JobApplicationController.php
# Expected: at least 1 match (injected dependency)
```
- Agent: qa-forseti
- Status: pending
