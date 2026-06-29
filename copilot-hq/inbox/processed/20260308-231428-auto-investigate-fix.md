# Command

- created_at: 2026-03-08T23:14:29-04:00
- work_item: dungeoncrawler-auto-investigation
- topic: auto-investigate-fix

## Command text
[AUTO-INVESTIGATION] Release KPI stagnation for dungeoncrawler (dungeoncrawler).
run_id=20260308-210214, open_issues=2, dev_status=done, unanswered_alerts=6, escalation_depth=0.

Autonomous directives (execute in order):
  1. Investigate why KPI is stagnant. Check dev outbox, run QA audit, apply any committed fixes.

Dev outbox excerpt:
The `fetchAll()` fix is applied but the error persists. The root cause is that mysqlnd requires explicit `closeCursor()` even after `fetchAll()` - freeing the `PDOStatement` object (via `unset`) is the reliable way to trigger `mysql_free_result()`. Let me implement the proper fix across all statement usages.

The fix needs explicit `unset()` of the statement variable to guarantee mysqlnd frees the cursor via the PDOStatement destructor. Let me fix all statement usages in `GameObjectInventoryService.php`:

Now fix `getTableRowCount()` to also explicitly `unset()` the statement:

Now verify the final state of the file looks correct, then clear caches and test:

The 500 persists. Let me get the full error from WID 380681:

The 500 happened but the drush login created new WIDs. Let me get the most recent error after the page test:

The **real root cause is now clear**: `PHP Fatal error: Allowed memory size of 134217728 bytes exhausted` at `PdoTrait.php:109` (inside `fetchAll()`) — loading 100 rows of LONGTEXT columns exhausts the 128MB memory limit. PHP OOM-kills the process mid-`fetchAll()`, leaving the cursor open, then `session_write_close()` fires during emergency shutdown and fails.

The fix: truncate LONGTEXT/BLOB columns to 255 chars in the list view query:

Bug 1 is **fixed** — HTTP 200. Now let me verify Bug 2 (the `Url::fromUri()` invalid URI fix is already applied) and test it:

Both bugs are fixed. Let me verify no new watchdog errors and check the Bug 2 fix is in the controller:

WID 380686 is from 02:35 (before our latest fix). Both bugs are now resolved. Let me commit the fixes:

Now write the outbox and commit to HQ:

---

- Status: done
- Summary: Fixed both real bugs from QA batch `20260308-205713`. Bug 1's true root cause was PHP OOM — `loadTableRows()` fetched 100 rows of LONGTEXT data (five columns up to megabytes each) and exhausted the 128MB memory limit mid-`fetchAll()`, leaving the PDO cursor open. PHP's OOM emergency shutdown then fired `sessio
...[truncated]
