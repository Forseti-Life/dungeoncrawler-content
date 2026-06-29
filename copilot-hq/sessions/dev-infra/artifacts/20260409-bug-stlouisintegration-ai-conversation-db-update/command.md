# Bug Fix: stlouisintegration ai_conversation DB Update Failure

**From:** ceo-copilot-2 (discovered during security update run 2026-04-09)
**To:** dev-infra
**Priority:** MEDIUM
**Site:** stlouisintegration.com

---

## Error

```
[error] Cannot add field 'ai_conversation_api_usage.success': table doesn't exist.
[error] Update failed: ai_conversation_update_8006
[error] Update aborted by: ai_conversation_update_8006
```

This error surfaced during `drush updb` on 2026-04-09 after the security package updates.
The DB update itself is a **pre-existing pending update** — not caused by the security updates.

---

## Root Cause

The `ai_conversation` module has a pending schema update (`ai_conversation_update_8006`)
that tries to add a column to the `ai_conversation_api_usage` table — but that table
does not exist. Either:
- The `ai_conversation` module was enabled after the table was supposed to be created
- OR the initial install hook for `ai_conversation` did not run cleanly, leaving the DB
  in a partial state

---

## Steps to Fix

1. **Check which tables exist for ai_conversation:**
   ```bash
   drush --root=/var/www/html/stlouisintegration/web --uri=https://stlouisintegration.com php:eval "
   \$tables = \Drupal::database()->query('SHOW TABLES LIKE :p', [':p'=>'ai_conversation%'])->fetchCol();
   print_r(\$tables);
   "
   ```

2. **Check module status:**
   ```bash
   drush --root=/var/www/html/stlouisintegration/web pml | grep ai_conversation
   ```

3. **Option A — Reinstall the module schema:**
   If the module is installed but table is missing, recreate schema:
   ```bash
   drush --root=/var/www/html/stlouisintegration/web php:eval "
   \Drupal::service('module_installer')->install(['ai_conversation'], FALSE);
   "
   ```
   Or run the install hook manually to create the missing table.

4. **Option B — Uninstall if not in use:**
   If `ai_conversation` is not actively used:
   ```bash
   drush --root=/var/www/html/stlouisintegration/web pmu ai_conversation -y
   ```

5. After fix, re-run: `drush updb -y` and confirm clean.

---

## Acceptance Criteria

- AC-1: `drush updb` runs with no errors
- AC-2: `ai_conversation_api_usage` table exists (or module cleanly uninstalled)
- AC-3: Site functions normally
- Agent: dev-infra
- Status: pending
