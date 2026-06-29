# DC-RG-01: Add dc_sessions table to hook_schema()

## Finding

**Severity:** MEDIUM  
**Release:** dungeoncrawler-release-g  
**Commit:** `0e176e09b` (feat: implement dc-cr-session-structure)

The `dc_sessions` table is created in `hook_update_10037()` for existing installs but is **absent from `hook_schema()`** in `dungeoncrawler_content.install`. Fresh Drupal installs will not get the `dc_sessions` table, causing all session-structure API calls to fail.

This is the same pattern as DC-RF-01 (feature_id column), which was fixed in `3b643f044`.

## Required fix

In `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.install`:

Add the `dc_sessions` table definition to `dungeoncrawler_content_schema()`. The exact schema definition is already present verbatim in `dungeoncrawler_content_update_10037()` — copy and include it in the `hook_schema()` return array under key `'dc_sessions'`.

## Acceptance criteria

1. `dungeoncrawler_content_schema()` returns an entry for `'dc_sessions'` with all columns matching those in `update_10037`.
2. `update_10037` retains the `tableExists` guard and is unchanged.
3. No other tables currently missing from `hook_schema()` (check `combat_afflictions` and `field_char_research_field` while you're in the file — if those are also missing, fix them in the same commit with numbered finding notes).
4. Verified by: `grep -n "dc_sessions\|combat_afflictions" dungeoncrawler_content.install | grep -v "^2[0-9][0-9][0-9]:"` should show `dc_sessions` appearing in lines < 2603 (inside `hook_schema` body).

## Context

- Pattern reference: `3b643f044` (DC-RF-01 fix for `feature_id`)
- File: `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.install`
- `hook_schema()` runs lines 39–2602; `hook_update_10037` creates the table starting at line ~4821.

## ROI

ROI: 8 — Fresh-install reliability gate; same class of defect as DC-RF-01 which was already fixed once.
- Agent: dev-dungeoncrawler
- Status: pending
