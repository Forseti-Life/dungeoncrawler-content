# Suite Activation: forseti-ai-service-db-refactor

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-08T18:09:15+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-ai-service-db-refactor"`**  
   This links the test to the living requirements doc at `features/forseti-ai-service-db-refactor/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-ai-service-db-refactor-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-ai-service-db-refactor",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-ai-service-db-refactor"`**  
   Example:
   ```json
   {
     "id": "forseti-ai-service-db-refactor-<route-slug>",
     "feature_id": "forseti-ai-service-db-refactor",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-ai-service-db-refactor",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-ai-service-db-refactor

KB reference: none found (new refactor category for ai_conversation module).

## AC-1 — Zero direct DB calls in AIApiService

**Given** `src/Service/AIApiService.php` (or equivalent) after the refactor,
**When** I search for direct DB query calls (`$this->database`, `\Drupal::database()`, raw `->query()`),
**Then** zero matches are found.

**Verification:** `grep -c 'database\(\)' sites/forseti/web/modules/custom/ai_conversation/src/Service/AIApiService.php` → 0.

## AC-2 — New query service/repository created

**Given** the refactored module,
**When** I look for a new class (e.g., `AiConversationRepository.php` or similar),
**Then** the class exists and contains the 14 migrated query methods.

**Verification:** Dev provides path and method list in implementation notes.

## AC-3 — AI conversation routes unaffected

**Given** the existing AI conversation routes at `https://forseti.life/talk-with-forseti`,
**When** an authenticated user accesses the route after deployment,
**Then** it responds correctly (200 or expected auth redirect; no 500).

**Verification:** QA smoke test: `curl -I https://forseti.life/talk-with-forseti` → not 500. Auth route returns expected response.

## AC-4 — Existing behavior unchanged

**Given** any AI conversation feature previously working in production,
**When** exercised after the refactor,
**Then** it behaves identically to pre-refactor.

**Verification:** QA regression check via site audit crawl — no new failures on ai_conversation routes.
