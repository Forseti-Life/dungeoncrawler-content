# Test Plan — forseti-ai-service-refactor

- Feature: Extract DB queries from AIApiService into AIConversationStorageService
- QA owner: qa-forseti
- Release: NEXT-RELEASE (grooming-only — do NOT activate suite entries until Stage 0)
- Date written: 2026-04-06
- KB references:
  - None found directly relevant to service extraction pattern in ai_conversation module.
  - Note: Bedrock model fix (commit `a4a4e8bf`, 2026-04-05) updated `ai_conversation` module — Dev must rebase on latest main before this refactor to avoid conflict.

## Pre-implementation grooming scan

**AIConversationStorageService:** does NOT exist yet (as expected — this is a new class).
**AIApiService.php direct DB calls:** `grep -c "\\Drupal::database()"` → 3 matches. AC states 14 total DB queries (may use additional patterns: `->db`, query builder chains, etc.). Dev must confirm full audit of all 14 before extraction is complete.
**Test directory:** `web/modules/custom/ai_conversation/tests/` does not exist — Dev must create it (Unit + Functional directories) before TC-01 and TC-02 can be automated.

**No ACL changes expected.** This is a pure internal refactor; no new routes, no permission changes, no `qa-permissions.json` entries needed.

## Scope
- In scope: Verify structural refactor is complete (static checks); verify behavioral parity (conversation history loads, new records persist, no data loss); verify typed exception on DB failure.
- Out of scope: AI model selection logic, API call behavior, `GenAiDebugController` (tracked as `forseti-ai-debug-gate`), caller signature changes (none expected).

## Test Matrix
- Browsers/devices: N/A (internal service; no UI interaction)
- Roles/permissions: authenticated (has `access ai conversation`), anonymous (no access — no change expected)
- Environments: localhost/Drupal functional test runner; production smoke check (conversation loads correctly) at Gate 2

## Routes / surfaces affected
- None new. Existing routes served by `ai_conversation` module are unchanged.
- Smoke check: `/talk-with-forseti` (chat surface) must continue loading for authenticated users.

---

## Test Cases

### TC-01 — Static: AIConversationStorageService class exists
- Suite (Stage 0): PHPUnit Unit — new file existence check or class autoload test
- AC item: `[NEW]` `AIConversationStorageService` exists at `src/Service/AIConversationStorageService.php`
- Steps: `ls web/modules/custom/ai_conversation/src/Service/AIConversationStorageService.php`
- Expected: File exists; `php -r "require 'web/autoload.php'; new \Drupal\ai_conversation\Service\AIConversationStorageService(...)"` does not throw
- Notes: Static check at Gate 2; also covered by Dev running `drush cr` without PHP fatal errors.

### TC-02 — Static: AIApiService has no direct \Drupal::database() calls
- Suite (Stage 0): PHPUnit Unit or grep-based script
- AC item: `[EXTEND]` `AIApiService` no longer contains `\Drupal::database()` or `db_*` calls
- Steps:
  ```bash
  grep -n "\\Drupal::database()\|db_query\|db_select\|db_insert\|db_update\|db_delete" \
    web/modules/custom/ai_conversation/src/Service/AIApiService.php
  ```
- Expected: 0 matches
- Notes: At grooming time, 3 `\Drupal::database()` matches exist — all must be removed by Dev. AC also cites 14 total queries; Dev must audit for all DB patterns, not just `\Drupal::database()`.

### TC-03 — Static: AIApiService references AIConversationStorageService (DI)
- Suite (Stage 0): grep-based script or Unit test
- AC item: `[EXTEND]` `AIApiService` uses `AIConversationStorageService` instead of direct DB
- Steps:
  ```bash
  grep "AIConversationStorageService" \
    web/modules/custom/ai_conversation/src/Service/AIApiService.php
  ```
- Expected: At least 1 match (injected dependency in constructor)

### TC-04 — Behavioral: Conversation history loads for existing user (no data loss)
- Suite (Stage 0): PHPUnit Functional — `AIServiceRefactorTest.php`
- AC item: `[TEST-ONLY]` Conversation history loads correctly for a user with an existing conversation
- Steps:
  1. Create authenticated user; POST a conversation message via `ai_conversation` module
  2. Load conversation history via `AIApiService` public method
  3. Confirm returned history matches stored records
- Expected: Conversation records returned correctly; no 500, no empty history when records exist
- Roles covered: authenticated

### TC-05 — Behavioral: New conversation record is created and persisted
- Suite (Stage 0): PHPUnit Functional — `AIServiceRefactorTest.php`
- AC item: `[TEST-ONLY]` New conversation record is created and persisted without error
- Steps:
  1. Authenticate user with `access ai conversation`
  2. Trigger new conversation via `AIApiService` public method
  3. Query DB directly to confirm row was written
- Expected: Row exists in ai_conversation table; no PHP error or exception
- Roles covered: authenticated

### TC-06 — Failure mode: DB write failure throws typed exception
- Suite (Stage 0): PHPUnit Unit — mock DB to throw; assert typed exception
- AC item: `[TEST-ONLY]` If DB write fails, `AIConversationStorageService` throws a typed exception (not raw PDO exception)
- Steps:
  1. Mock `\Drupal\Core\Database\Connection` to throw `\Exception` on `insert()`
  2. Call storage service method that wraps a DB write
  3. Assert that a typed exception (custom or Drupal-typed) is thrown, not a raw `\PDOException`
- Expected: Typed exception thrown; error is catchable without exposing raw PDO internals
- Notes: Requires PHPUnit Unit setup with mocked DB. No Functional test needed for this case.

### TC-07 — Caller parity: AIConversation chat surface functional after refactor
- Suite (Stage 0): role-url-audit (smoke check) + existing jobhunter-e2e if applicable
- AC item: `[EXTEND]` All existing callers of `AIApiService` public methods continue to work without signature changes
- Steps:
  1. Load `/talk-with-forseti` as authenticated user
  2. Confirm page returns HTTP 200 (no 500 from broken service wiring)
  3. Confirm no new PHP errors in watchdog after deploy
- Expected: HTTP 200; 0 new error/critical watchdog entries
- Roles covered: authenticated
- Notes: Covered partially by existing `role-url-audit` (talk-with-forseti rule). Full smoke requires authenticated session.

### TC-08 — No ACL regression: anonymous behavior unchanged
- Suite (Stage 0): role-url-audit (existing rule)
- AC item: `[TEST-ONLY]` No ACL changes — same permissions as before
- Steps: Run role-url-audit; confirm `/talk-with-forseti` still returns expected response for anon role (deny/redirect per existing rule)
- Expected: No regression in anon behavior
- Roles covered: anonymous

---

## Suite activation plan (Stage 0, next release)

| TC | Suite | Activation action |
|---|---|---|
| TC-01 | grep script or Unit | New suite entry: `forseti-ai-service-refactor-static` (script type) |
| TC-02 | grep script | Include in `forseti-ai-service-refactor-static` command |
| TC-03 | grep script | Include in `forseti-ai-service-refactor-static` command |
| TC-04, TC-05 | PHPUnit Functional | New suite entry: `forseti-ai-service-refactor-functional`; Dev creates test dir + `AIServiceRefactorTest.php` |
| TC-06 | PHPUnit Unit | New suite entry: `forseti-ai-service-refactor-unit`; Dev creates `tests/src/Unit/` directory |
| TC-07 | role-url-audit | Existing rule covers smoke check; confirm `talk-with-forseti` rule is present in `qa-permissions.json` |
| TC-08 | role-url-audit | Existing rule — no new entry needed |

**No qa-permissions.json entries needed** — no new routes, no permission changes.

## Pass/Fail Criteria
- TC-01 PASS: `AIConversationStorageService.php` exists, PHP-loadable
- TC-02 PASS: 0 direct DB calls in `AIApiService`
- TC-03 PASS: `AIConversationStorageService` injected into `AIApiService`
- TC-04 PASS: Conversation history correct after refactor
- TC-05 PASS: New record persisted without error
- TC-06 PASS: Typed exception thrown on DB failure
- TC-07 PASS: Chat surface returns 200; 0 new watchdog errors
- TC-08 PASS: Anon behavior unchanged (no regression)
- FAIL trigger: Any 500 from `ai_conversation` routes; raw PDO exception exposed; existing conversation history missing; `AIApiService` still contains direct DB queries.

## Manual Tests (non-SoT)
- End-to-end chat test with real Bedrock API call (requires live AWS credentials) — not automated in CI. Manual smoke check at Gate 2 by QA or human operator.

## Notes to PM
- Dev must create `web/modules/custom/ai_conversation/tests/src/Unit/` and `tests/src/Functional/` directories before TC-04 through TC-06 can be automated. Flag this as a Stage 0 prerequisite.
- At grooming time, `AIApiService` has 3 `\Drupal::database()` matches and no `AIConversationStorageService` — as expected for pre-implementation. All static checks will fail until Dev completes the refactor.
- KB note: rebase on `main` (post-Bedrock fix `a4a4e8bf`) is required before starting this feature to avoid merge conflicts in `AIApiService.php`.
