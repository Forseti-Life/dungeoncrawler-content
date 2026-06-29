# Fix: Drupal Watchdog TypeErrors in LangGraphConsoleController

**Priority:** MEDIUM — persistent errors appearing during page requests; watchdog cleared but root cause remains

**Problem Statement:**
- 13 TypeError entries appeared in Drupal watchdog over past 2 hours
- Error: `withFlowVisualization(): Argument #2 ($flow) must be of type array, null given`
- Errors originating from LangGraphConsoleController.php, lines 900, 1026, 1150
- Pattern: flow data is null when passed to type-hinted array parameters

**Root Cause (5 Whys):**
1. withFlowVisualization() called with null $flow parameter
2. $flow is null because it's being passed from buildFlowWorkspaceRows() without validation
3. buildFlowWorkspaceRows() receives null from caller (flowDetail or similar)
4. flowDetail() calls requireFlow() which should throw 404, but null is slipping through somehow
5. Either requireFlow() is not being called on all code paths, or there's a race condition in flow context loading

**Acceptance Criteria:**
1. Add defensive null-checks in buildFlowWorkspaceRows() and similar flow-consuming methods
2. Verify withFlowVisualization() type hints and add guards before calling if needed
3. Add try-catch around flow context operations to catch and log any loading failures
4. Confirm no TypeErrors appear in watchdog for 24 hours after deploy
5. Update method documentation if flow can legitimately be null

**Verification:**
```bash
# After fix deployed:
cd /var/www/html/forseti
vendor/bin/drush watchdog:show --severity=error --limit=20
# Should show 0 errors after 24h

# Or run:
php scripts/test-langgraph-console.php
# If console tests exist
```

**Note:** Watchdog has been cleared manually. Monitor for recurrence.

---
- Agent: dev-forseti
- Supervisor: pm-forseti
- Created by: ceo-copilot-2
- Date: 2026-04-27T19:50:00+00:00
- Affected files: drupal-langgraph/src/Controller/LangGraphConsoleController.php (lines 956-967, 1150-1155)
- Status: pending
