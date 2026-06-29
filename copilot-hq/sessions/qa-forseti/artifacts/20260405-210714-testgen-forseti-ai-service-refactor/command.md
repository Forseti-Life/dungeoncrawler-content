# Test Plan Design: forseti-ai-service-refactor

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-05T21:07:14+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/forseti-ai-service-refactor/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh forseti forseti-ai-service-refactor "<brief summary>"
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
# Feature: forseti-ai-service-refactor

## Gap analysis reference

Gap analysis in `feature.md` is complete. Criteria are `[NEW]` for the new service class and `[EXTEND]` for caller updates.

## Happy Path

- [ ] `[NEW]` `AIConversationStorageService` class exists at `web/modules/custom/ai_conversation/src/Service/AIConversationStorageService.php`.
- [ ] `[NEW]` All 14 DB queries previously inline in `AIApiService` are now delegated to `AIConversationStorageService`.
- [ ] `[EXTEND]` `AIApiService` no longer contains direct `\Drupal::database()` or `db_*` calls — it uses `AIConversationStorageService` instead.
- [ ] `[EXTEND]` All existing callers of `AIApiService` public methods continue to work without changes to their call signatures.

## Edge Cases

- [ ] `[TEST-ONLY]` Conversation history loads correctly for a user with an existing conversation (no data loss from refactor).
- [ ] `[TEST-ONLY]` New conversation record is created and persisted without error.

## Failure Modes

- [ ] `[TEST-ONLY]` If DB write fails, `AIConversationStorageService` throws a typed exception (not a raw PDO exception).

## Permissions / Access Control

- [ ] `[TEST-ONLY]` No ACL changes — storage service is internal; same permissions as before apply via `AIApiService`.

## Data Integrity

- [ ] No data migration required — storage service queries same tables.
- [ ] Rollback path: revert service extraction commit; `AIApiService` inline queries restored.

## Knowledgebase check

- None found directly relevant; note that the Bedrock model fix (2026-04-05) updated `ai_conversation` module — dev must rebase on latest `main` before this refactor.

## Verification method

```bash
# After refactor:
grep -r "\\\\Drupal::database()" web/modules/custom/ai_conversation/src/Service/AIApiService.php
# Expected: 0 matches (all moved to storage service)
grep -r "AIConversationStorageService" web/modules/custom/ai_conversation/src/Service/AIApiService.php
# Expected: at least 1 match (injected dependency)
```
- Agent: qa-forseti
- Status: pending
