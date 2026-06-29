# Suite Activation: forseti-ai-debug-gate

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-07T18:27:02+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-ai-debug-gate"`**  
   This links the test to the living requirements doc at `features/forseti-ai-debug-gate/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-ai-debug-gate-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-ai-debug-gate",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-ai-debug-gate"`**  
   Example:
   ```json
   {
     "id": "forseti-ai-debug-gate-<route-slug>",
     "feature_id": "forseti-ai-debug-gate",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-ai-debug-gate",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan — forseti-ai-debug-gate

- Feature: Verify and lock GenAiDebugController to admin-only access
- QA owner: qa-forseti
- Release target: 20260405-forseti-release-c
- Date written: 2026-04-06
- KB references:
  - `knowledgebase/lessons/20260227-routing-permission-mismatch-companyresearch.md` — watch for `_permission` mismatches in routing YAML

## Notes to PM

**Grooming-time scan result:** All 4 `GenAiDebugController` routes in `ai_conversation.routing.yml` already carry `_permission: 'administer site configuration'`. POST routes additionally have `_csrf_token: 'TRUE'`. Static check AC passes immediately.

Routes confirmed:
- `ai_conversation.genai_debug_list` → `GET /admin/reports/genai-debug` — `_permission: 'administer site configuration'`
- `ai_conversation.genai_debug_detail` → `GET /admin/reports/genai-debug/{id}` — `_permission: 'administer site configuration'`
- `ai_conversation.genai_debug_delete` → `POST /admin/reports/genai-debug/{id}/delete` — `_permission: 'administer site configuration'` + `_csrf_token: 'TRUE'`
- `ai_conversation.genai_debug_delete_all` → `POST /admin/reports/genai-debug/delete-all` — `_permission: 'administer site configuration'` + `_csrf_token: 'TRUE'`

**Automation gap flagged:** `content_editor` role has `_permission: 'access administration pages'` which grants access to `/admin/...` paths in general, but NOT `administer site configuration`. TC-03 must confirm content_editor is denied; this requires a distinct authenticated cookie for the `content_editor` role. If the `role-url-audit` suite has no `content_editor` cookie configured at Gate 2, TC-03 will be marked "manual/deferred" in the verification report.

**No Dev action required** unless a route is found misconfigured. This is `needs-testing` — current implementation appears correct.

---

## Test Cases

### TC-01 — Static routing YAML check
- **Suite:** script (grep/static analysis)
- **AC item:** `grep "GenAiDebugController" ai_conversation.routing.yml` — every route shows `_permission: 'administer site configuration'`
- **Steps:**
  1. `grep -A8 'GenAiDebugController' web/modules/custom/ai_conversation/ai_conversation.routing.yml | grep _permission`
- **Expected:** Every block shows `_permission: 'administer site configuration'`; no block shows a weaker permission or missing `_permission`
- **Roles covered:** N/A (static check)
- **Automated:** Yes — single grep invocation; add to release preflight script or `role-url-audit` pre-check

### TC-02 — Anonymous GET → 403
- **Suite:** `role-url-audit` (`ai-debug-routes` rule in `qa-permissions.json`)
- **AC item:** Anonymous user behavior: 403 on all `GenAiDebugController` routes
- **Steps:**
  1. GET `/admin/reports/genai-debug` without any session cookie
  2. GET `/admin/reports/genai-debug/1` without any session cookie
- **Expected:** HTTP 403 (or 302 redirect to `/user/login` which QA audit scores as deny — confirm rule expectation is `deny`)
- **Roles covered:** anon
- **Automated:** Yes — `role-url-audit` suite; `ai-debug-routes` rule already added to `qa-permissions.json` (commit `c0b01ac1`)

### TC-03 — Authenticated non-admin GET → 403
- **Suite:** `role-url-audit` (`ai-debug-routes` rule) + PHP functional test (`GenAiDebugAccessTest.php`)
- **AC item:** Authenticated non-admin behavior: 403 on all `GenAiDebugController` routes
- **Steps:**
  1. Authenticate as basic `authenticated` user (no special roles)
  2. GET `/admin/reports/genai-debug`
  3. GET `/admin/reports/genai-debug/1`
- **Expected:** HTTP 403 for both
- **Roles covered:** authenticated
- **Automated:** Yes (role-url-audit with authenticated cookie; PHP BrowserTest)

### TC-04 — content_editor GET → 403
- **Suite:** `role-url-audit` (`ai-debug-routes` rule, `content_editor: deny` already set)
- **AC item:** Authenticated non-admin behavior: 403 — content_editor has admin page access but NOT `administer site configuration`
- **Steps:**
  1. Authenticate as `content_editor` role user
  2. GET `/admin/reports/genai-debug`
- **Expected:** HTTP 403
- **Roles covered:** content_editor
- **Automated:** Yes — role-url-audit with content_editor cookie; note `FORSETI_COOKIE_CONTENT_EDITOR` env var required at Gate 2
- **Note to PM:** If `content_editor` cookie is unavailable at Gate 2, this test case is deferred to manual verification. Not blocking for release if TC-03 (authenticated) and TC-05 (admin) pass and static check is clean.

### TC-05 — Admin GET → 200
- **Suite:** `role-url-audit` (`ai-debug-routes` rule, `administrator: allow`) + PHP functional test
- **AC item:** Admin behavior: 200 on all `GenAiDebugController` routes
- **Steps:**
  1. Authenticate as administrator
  2. GET `/admin/reports/genai-debug`
  3. GET `/admin/reports/genai-debug/1` (may return 404 if no records — treat 200 or 404 as PASS; 403 is FAIL)
- **Expected:** HTTP 200 for list route; 200 or 404 for detail route (no 403)
- **Roles covered:** administrator
- **Automated:** Yes — role-url-audit; PHP BrowserTest

### TC-06 — PHP functional test suite (`GenAiDebugAccessTest.php`)
- **Suite:** PHP functional test
- **AC item:** QA test path from AC — Admin 200, authenticated non-admin 403, anonymous 403
- **Steps:** Implement `GenAiDebugAccessTest.php` covering at minimum `genai_debug_list` route
  1. `$this->drupalGet('/admin/reports/genai-debug')` as admin → assert 200
  2. `$this->drupalGet('/admin/reports/genai-debug')` as authenticated non-admin → assert 403
  3. `$this->drupalGet('/admin/reports/genai-debug')` as anonymous → assert 403 or 302
- **Expected:** All three assertions pass
- **Roles covered:** anon, authenticated, administrator
- **Automated:** Yes — PHP BrowserTest; must exist and pass at Gate 2

### TC-07 — Rollback path verification (config-only)
- **Suite:** Manual
- **AC item:** Rollback path: revert `ai_conversation.routing.yml` + `drush cr`
- **Steps:**
  1. Confirm no DB migration was required (this is routing config only)
  2. Document: rollback = git revert of routing yml change + `drush cr`
- **Expected:** No DB migration artifacts; rollback is clean
- **Automated:** No — document in verification report as config-only risk acceptance

---

## Suite activation plan (Stage 0, next release)

| Test case | Suite | Activation action |
|---|---|---|
| TC-01 | script | Add grep check to release preflight or audit pre-step |
| TC-02 | `role-url-audit` | `ai-debug-routes` rule already in `qa-permissions.json` (commit `c0b01ac1`) |
| TC-03 | `role-url-audit` + PHP test | `ai-debug-routes` rule covers authenticated; add `GenAiDebugAccessTest.php` suite entry |
| TC-04 | `role-url-audit` | `ai-debug-routes` rule has `content_editor: deny`; requires `FORSETI_COOKIE_CONTENT_EDITOR` at runtime |
| TC-05 | `role-url-audit` + PHP test | `ai-debug-routes` rule has `administrator: allow` |
| TC-06 | PHP functional test | Add `GenAiDebugAccessTest.php` suite entry to `suite.json` |
| TC-07 | manual | Document in verification report; no suite entry needed |

## Acceptance summary

All AC items covered:
- Static YAML check: TC-01
- Admin access (200): TC-05, TC-06
- Authenticated non-admin deny (403): TC-03, TC-04, TC-06
- Anonymous deny (403): TC-02, TC-06
- Rollback: TC-07 (manual, config-only)
- No Dev action required if grooming-time state is unchanged at Gate 2

### Acceptance criteria (reference)

# Acceptance Criteria — forseti-ai-debug-gate

- Feature: Verify and lock GenAiDebugController to admin-only access
- Release target: 20260402-forseti-release-b
- PM owner: pm-forseti
- Date groomed: 2026-04-05
- Priority: P1

## Gap analysis reference

Feature type: `needs-testing` — Implementation likely exists (admin routing). Need verification and regression tests.
All criteria are `[TEST-ONLY]` unless Dev must correct a misconfigured route.

PM decision: GenAiDebugController remains in production code; all routes must require `administer site configuration` or equivalent admin permission. No risk acceptance for non-admin exposure.

## Knowledgebase check
- None found specific to `ai_conversation` module debug access.
- Related pattern: `knowledgebase/lessons/20260227-routing-permission-mismatch-companyresearch.md` — watch for route-level `_permission` mismatches in `ai_conversation.routing.yml`.

## Happy Path

- [ ] `[TEST-ONLY]` `grep "GenAiDebugController" ai_conversation.routing.yml` — every route shows `_permission: 'administer site configuration'` (or stricter).
- [ ] `[TEST-ONLY]` Admin user accessing `/admin/ai-conversation/debug` (or equivalent route): HTTP 200.

## Edge Cases

- [ ] `[TEST-ONLY]` Authenticated non-admin user accessing any `GenAiDebugController` route: HTTP 403.

## Failure Modes

- [ ] `[TEST-ONLY]` Anonymous user accessing any `GenAiDebugController` route: HTTP 403.
- [ ] `[TEST-ONLY]` If a route is found with non-admin permission during Dev audit: escalate as P0 blocker; do not accept risk.

## Permissions / Access Control

- [ ] Anonymous user behavior: 403 on all `GenAiDebugController` routes.
- [ ] Authenticated non-admin behavior: 403 on all `GenAiDebugController` routes.
- [ ] Admin behavior: 200 on all `GenAiDebugController` routes.

## Data Integrity

- [ ] No DB migration required. Routing config change only.
- [ ] Rollback path: revert `ai_conversation.routing.yml` + `drush cr`.

## Dev definition of done
- [ ] All `GenAiDebugController` routes in `ai_conversation.routing.yml` confirmed to require admin permission.
- [ ] If any route requires a fix: commit the routing change, update notes.

## QA test path
- `web/modules/custom/ai_conversation/tests/src/Functional/GenAiDebugAccessTest.php` — new test:
  - Admin: 200 on at least one debug route.
  - Authenticated non-admin: 403.
  - Anonymous: 403.
