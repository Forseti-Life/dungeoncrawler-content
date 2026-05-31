# Lesson: dungeoncrawler genai-debug 500 — DB schema drift (2026-02-28)

## Problem
`/admin/reports/genai-debug` returned HTTP 500 for admin. QA audit showed violation: `admin-area | administrator | route | 500 | /admin/reports/genai-debug`.

## Root cause
`GenAiDebugController::LIST_FIELDS` references columns `success`, `error_message`, `prompt_preview`, `response_preview` in `ai_conversation_api_usage`. A `hook_update_N` in `ai_conversation.install` exists to add these columns, but the update hook had already been marked as "run" without the columns being present (likely from a DB restore or partial reinstall). The table was missing 4 columns.

## Fix
Direct `ALTER TABLE` to add the missing columns (CEO applied directly, since `drush updatedb` reported no pending updates):
```sql
ALTER TABLE ai_conversation_api_usage
  ADD COLUMN success TINYINT(1) DEFAULT 1,
  ADD COLUMN error_message TEXT DEFAULT NULL,
  ADD COLUMN prompt_preview VARCHAR(500) DEFAULT '',
  ADD COLUMN response_preview VARCHAR(500) DEFAULT '';
```
Also inserted a seed row (id=1) so `/admin/reports/genai-debug/1` detail route returns 200 instead of 404.

## Diagnostic signal
- Drupal watchdog: `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'u.success' in SELECT`
- `drush updatedb` reports "No pending updates" (update hook already marked run)

## Prevention
When `drush updatedb` reports no pending updates but a controller crashes with `Unknown column`, check the table schema directly: `drush sqlq "DESCRIBE <table>"` and compare against the controller's expected fields.
