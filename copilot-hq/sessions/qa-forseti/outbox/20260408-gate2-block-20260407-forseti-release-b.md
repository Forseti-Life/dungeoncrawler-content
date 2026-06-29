# Gate 2 Verification Report — 20260407-forseti-release-b

- Release: 20260407-forseti-release-b
- QA seat: qa-forseti
- Gate: Gate 2 — Verification
- Verdict: **BLOCK**
- Date: 2026-04-08T00:18:00+00:00

## BLOCK — Evidence

### forseti-csrf-fix — PASS (static)
- TC-01: All 7 POST routes have `_csrf_token: TRUE`
  ```
  PASS: step3_post
  PASS: step3_short_post
  PASS: step4_post
  PASS: step4_short_post
  PASS: step5_post
  PASS: step5_short_post
  PASS: step_stub_short_post
  ```
- Functional suite (TC-02/03/04/06): STAGE 0 PENDING — PHPUnit infra blocker + `CsrfApplicationSubmissionTest.php` not yet created by Dev.

### forseti-ai-debug-gate — CONDITIONAL PASS (static only)
- GenAiDebugController.php: EXISTS
- Routes: `ai_conversation.genai_debug_list` / `ai_conversation.genai_debug_detail` — `_permission: 'administer site configuration'` confirmed
- Functional suite (PHPUnit): STAGE 0 PENDING — phpunit infra blocker, test files not created.
- Static checks PASS. Full functional verification not possible (infra gap).

### forseti-ai-service-refactor — FAIL (not implemented)
- `AIConversationStorageService.php`: **MISSING** at `web/modules/custom/ai_conversation/src/Service/`
- `AIApiService.php` direct DB calls: **3 matches** (`\Drupal::database()` still present)
- AC requires: 0 direct DB calls in AIApiService, AIConversationStorageService injected, all 14 queries extracted.
- Dev has not implemented this feature. Feature status: `ready` (awaiting implementation).

### forseti-jobhunter-schema-fix — FAIL (not implemented)
- No `age_18_or_older` column addition found in `job_hunter.install`.
- No update hook adding the missing DB column detected.
- Feature status: `ready` (awaiting Dev implementation).

## Suite coverage (all entries exist)

| Feature | Suite entries | Runnable now |
|---|---|---|
| forseti-csrf-fix | forseti-csrf-fix-route-acl, forseti-csrf-fix-functional | route-acl only (static TC-01) |
| forseti-ai-debug-gate | forseti-ai-debug-gate-route-acl, -static, -functional | static only |
| forseti-ai-service-refactor | forseti-ai-service-refactor-static, -functional, -unit | static (but FAIL — files missing) |
| forseti-jobhunter-schema-fix | forseti-jobhunter-schema-fix-functional | none (PHPUnit infra blocker) |

## Blockers requiring Dev action before re-verification

1. **forseti-ai-service-refactor**: Dev must create `AIConversationStorageService.php` and remove all `\Drupal::database()` calls from `AIApiService.php`.
2. **forseti-jobhunter-schema-fix**: Dev must add update hook to `job_hunter.install` adding the missing `age_18_or_older` DB column (or correct the field definition to match existing column).
3. **phpunit infra**: `vendor/bin/phpunit` absent at `/var/www/html/forseti/vendor/`. Blocks all PHPUnit functional/unit suites. pm-forseti must escalate `composer install` to pm-infra.

## Release-b recommendation

**Do not proceed to release signoff** until forseti-ai-service-refactor and forseti-jobhunter-schema-fix are implemented and static checks pass.

Options for pm-forseti:
1. Defer both unimplemented features to next release and ship release-b with only forseti-csrf-fix + forseti-ai-debug-gate.
2. Hold release-b open for Dev implementation.

KB reference: `knowledgebase/lessons/20260301-jobhunter-routing-csrf-token-blocks-qa-probe.md` (CSRF token testing pattern)
