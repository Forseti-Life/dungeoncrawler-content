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
