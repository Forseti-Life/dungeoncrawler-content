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
