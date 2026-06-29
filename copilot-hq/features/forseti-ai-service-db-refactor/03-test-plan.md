# Test Plan: forseti-ai-service-db-refactor

**QA owner:** qa-forseti
**Release:** 20260408-forseti-release-i

## Test cases

### TC-1: No direct DB calls in AIApiService
- **Type:** static analysis
- **Command:** `grep -rn 'database' sites/forseti/web/modules/custom/ai_conversation/src/Service/AIApiService.php`
- **Expected:** Zero matches
- **Pass criteria:** Empty output (no direct DB calls)

### TC-2: New query service exists
- **Type:** code structure check
- **Method:** Verify new repository/query service file exists at path documented in implementation notes
- **Expected:** File exists, contains 14+ methods
- **Pass criteria:** `ls -la <repo-path>` exits 0

### TC-3: /talk-with-forseti returns expected response
- **Type:** functional smoke test (unauthenticated)
- **Command:** `curl -Is https://forseti.life/talk-with-forseti | head -1`
- **Expected:** HTTP 403 (auth-required, expected per qa-permissions.json) — NOT 500
- **Pass criteria:** Status 403, not 500

### TC-4: AI conversation routes return no 500s
- **Type:** regression
- **Method:** QA site audit crawl of all ai_conversation routes
- **Expected:** No 500 errors
- **Pass criteria:** Audit shows 0 server errors on ai_conversation routes

### TC-5: No config drift
- **Type:** regression
- **Command:** `drush config:status` at Drupal site root
- **Expected:** No unexpected configuration changes
- **Pass criteria:** Config status shows no drift related to ai_conversation
