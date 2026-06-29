# Test Plan: forseti-langgraph-console-release-panel

- Feature: forseti-langgraph-console-release-panel
- Module: copilot_agent_tracker
- Author: qa-forseti
- Date: 2026-04-11
- KB reference: none found (new data-wiring pattern for LangGraph Console stub routes; follows `DashboardController::langgraphPath()` pattern from release-c)

## Route note

AC references `/langgraph-console/release` as a shorthand path. The **live Drupal route** is:
`/admin/reports/copilot-agent-tracker/langgraph-console/release`
All test cases use the full admin path. Permission: `administer copilot agent tracker` (typically granted to `administrator` role).

## Scope

Verify the Release Control Panel renders live release state data for all sites, enforces admin-only access, degrades gracefully when state files are absent, and exposes no filesystem paths in output.

---

## Test Cases

### TC-1: Admin GET → 200, correct title
- **Suite**: `role-url-audit`
- **Description**: Authenticated user with `administer copilot agent tracker` permission GETs `/admin/reports/copilot-agent-tracker/langgraph-console/release`.
- **Command**:
  ```bash
  CODE=$(curl -s -o /dev/null -w "%{http_code}" -b "$FORSETI_COOKIE_ADMIN" \
    https://forseti.life/admin/reports/copilot-agent-tracker/langgraph-console/release)
  echo "HTTP $CODE"; [ "$CODE" -eq 200 ] && echo PASS || (echo FAIL; exit 1)
  ```
- **Expected**: HTTP 200; page body contains "Release Control Panel".
- **Roles covered**: admin

### TC-2: Anonymous GET → 403
- **Suite**: `role-url-audit`
- **Description**: Unauthenticated GET to the release panel route.
- **Command**:
  ```bash
  CODE=$(curl -s -o /dev/null -w "%{http_code}" \
    https://forseti.life/admin/reports/copilot-agent-tracker/langgraph-console/release)
  echo "HTTP $CODE"; [ "$CODE" -eq 403 ] && echo PASS || (echo FAIL; exit 1)
  ```
- **Expected**: HTTP 403 (or redirect to /user/login yielding 403 after follow).
- **Roles covered**: anonymous

### TC-3: Authenticated non-admin → 403
- **Suite**: `role-url-audit`
- **Description**: Authenticated user without `administer copilot agent tracker` permission GETs the release panel.
- **Command**:
  ```bash
  CODE=$(curl -s -o /dev/null -w "%{http_code}" -b "$FORSETI_COOKIE_AUTHENTICATED" \
    https://forseti.life/admin/reports/copilot-agent-tracker/langgraph-console/release)
  echo "HTTP $CODE"; [ "$CODE" -eq 403 ] && echo PASS || (echo FAIL; exit 1)
  ```
- **Expected**: HTTP 403.
- **Roles covered**: authenticated (no admin perm)

### TC-4: Release panel rows rendered — per-site data columns
- **Suite**: `e2e` (Playwright)
- **Description**: Admin visits the release panel; for each site (forseti, dungeoncrawler), verify the rendered table row includes: `release_id`, `pm_signoff_status` (SIGNED or PENDING), `feature_count` (integer), `hours_elapsed` (numeric).
- **Command**: `npx playwright test --grep 'langgraph-console-release.*panel-rows' tests/forseti/langgraph-console-release-panel.spec.js`
- **Expected**: Table contains at least one row per site; all four data fields non-empty.
- **Roles covered**: admin

### TC-5: No active release → graceful "No active release" row (not PHP error)
- **Suite**: `e2e` (Playwright) or functional
- **Description**: When `tmp/release-cycle-active/<site>.release_id` does not exist for a site, the panel row shows "No active release" text instead of a PHP error or blank.
- **Command**: `npx playwright test --grep 'langgraph-console-release.*no-active-release' tests/forseti/langgraph-console-release-panel.spec.js`
- **Expected**: Row present; text "No active release" (or equivalent); no PHP exception markup.
- **Notes**: This may require a test-fixture approach (temp rename of state file) or be verified by confirming the controller has null-check logic — Dev to document in implementation notes.
- **Roles covered**: admin

### TC-6: Missing PM signoff file → PENDING (not error)
- **Suite**: `e2e` (Playwright) or functional
- **Description**: When `sessions/pm-<site>/artifacts/release-signoffs/<release_id>.md` does not exist, pm_signoff_status shown as "PENDING".
- **Command**: `npx playwright test --grep 'langgraph-console-release.*pending-signoff' tests/forseti/langgraph-console-release-panel.spec.js`
- **Expected**: Status column shows "PENDING"; no PHP error or blank cell.
- **Notes**: Same fixture caveat as TC-5 — Dev to document null-check in implementation notes.
- **Roles covered**: admin

### TC-7: Data freshness — no stale cache > 60s
- **Suite**: `functional`
- **Description**: Make two requests 5 seconds apart; data in the second request reflects any state-file changes made between the two requests.
- **Command**: Manual / scripted: update a state file between two curl requests and compare rendered output.
- **Notes**: Automated test requires ability to write to `tmp/release-cycle-active/` between requests. Mark as manual-verify until Dev documents cache TTL in implementation notes. Flag to PM: if Drupal render caching is active on this route, cache must be disabled or TTL ≤ 60s (AC-3).
- **Roles covered**: admin
- **PM note**: Dev must document cache strategy (no-cache or cache max-age ≤ 60s) in implementation notes before this TC can be fully automated.

### TC-8: Console navigation link present
- **Suite**: `e2e` (Playwright)
- **Description**: Admin visits the LangGraph console home (`/admin/reports/copilot-agent-tracker/langgraph-console`); a link to the release panel is present in navigation.
- **Command**: `npx playwright test --grep 'langgraph-console-release.*nav-link' tests/forseti/langgraph-console-release-panel.spec.js`
- **Expected**: `<a href="...langgraph-console/release">` present on console home or nav menu.
- **Roles covered**: admin

### TC-9: No filesystem paths in rendered HTML
- **Suite**: `functional`
- **Description**: Fetch the rendered HTML of the release panel as admin; verify no absolute filesystem paths (`/home/ubuntu`, `/var/www`, `COPILOT_HQ_ROOT` resolved paths) appear in output.
- **Command**:
  ```bash
  HTML=$(curl -s -b "$FORSETI_COOKIE_ADMIN" \
    https://forseti.life/admin/reports/copilot-agent-tracker/langgraph-console/release)
  echo "$HTML" | grep -E "/home/ubuntu|/var/www|COPILOT_HQ_ROOT" \
    && (echo FAIL; exit 1) || echo PASS
  ```
- **Expected**: No filesystem path strings in output. PASS.
- **Roles covered**: admin

### TC-10: No watchdog errors on clean admin render
- **Suite**: `regression`
- **Description**: Request the release panel as admin; verify no new severity ≤ 3 watchdog entries for `copilot_agent_tracker` module appear in the DB.
- **Command**:
  ```bash
  BEFORE=$(cd /var/www/html/forseti && vendor/bin/drush sql:query "SELECT MAX(wid) FROM watchdog" 2>/dev/null | tail -1)
  curl -s -o /dev/null -b "$FORSETI_COOKIE_ADMIN" \
    https://forseti.life/admin/reports/copilot-agent-tracker/langgraph-console/release
  AFTER=$(cd /var/www/html/forseti && vendor/bin/drush sql:query \
    "SELECT COUNT(*) FROM watchdog WHERE severity<=3 AND wid>$BEFORE AND type='copilot_agent_tracker'" \
    2>/dev/null | tail -1)
  echo "New errors: $AFTER"; [ "$AFTER" -eq 0 ] && echo PASS || (echo FAIL; exit 1)
  ```
- **Expected**: 0 new severity ≤ 3 entries for `copilot_agent_tracker`. PASS.
- **Roles covered**: admin

### TC-11: COPILOT_HQ_ROOT env var used — no hardcoded paths in controller
- **Suite**: `code-review` (static)
- **Description**: Review the controller file for the release panel; confirm all filesystem path construction uses `COPILOT_HQ_ROOT` or equivalent env var, not hardcoded paths.
- **Command**: Manual code inspection of `LangGraphConsoleStubController::release()` (or the new controller once dev ships).
- **Expected**: No literal `/home/ubuntu/`, `/var/www/`, or other hardcoded absolute paths in controller logic.
- **Notes**: Dev to confirm in implementation notes. If hardcoded paths found at Stage 4, flag as BLOCK.
- **Roles covered**: n/a (code review)

---

## PM Notes (items requiring Dev input before suite activation)

1. **TC-7 (cache freshness)**: Dev must document the cache strategy for this route in `02-implementation-notes.md`. If Drupal render cache is active, max-age must be ≤ 60s or cache must be disabled. Without this, TC-7 cannot be reliably automated.

2. **TC-5/TC-6 (missing state files)**: Dev must document null-check logic in implementation notes. Test fixtures (temporarily absent state files) are the preferred automation approach; if the controller is unit-tested, a unit test reference is acceptable.

3. **Exact route path**: AC says `/langgraph-console/release` (shorthand). Live route stub already exists at `/admin/reports/copilot-agent-tracker/langgraph-console/release`. Playwright selectors and curl commands use the full path. Dev should confirm this is the intended final path or update routing.

---

## Suite mapping summary

| TC   | Suite type        | Automatable at Stage 0? | Notes                          |
|------|-------------------|------------------------|-------------------------------|
| TC-1 | role-url-audit    | Yes (curl)              |                               |
| TC-2 | role-url-audit    | Yes (curl)              |                               |
| TC-3 | role-url-audit    | Yes (curl)              |                               |
| TC-4 | e2e (Playwright)  | Yes                     |                               |
| TC-5 | e2e / functional  | Partial — needs fixture | See PM note 2                 |
| TC-6 | e2e / functional  | Partial — needs fixture | See PM note 2                 |
| TC-7 | functional        | Partial — needs cache doc | See PM note 1               |
| TC-8 | e2e (Playwright)  | Yes                     |                               |
| TC-9 | functional (curl) | Yes                     |                               |
| TC-10 | regression       | Yes                     |                               |
| TC-11 | code-review      | Manual                  | Dev implementation notes      |

**Curl automatable (TC-1/2/3/9/10)**: 5 TCs  
**Playwright required (TC-4/5/6/7/8)**: 5 TCs  
**Manual/code-review (TC-11)**: 1 TC
