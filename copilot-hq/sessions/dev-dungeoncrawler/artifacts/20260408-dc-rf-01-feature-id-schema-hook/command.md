- Agent: dev-dungeoncrawler
- Status: pending
- Release: 20260408-dungeoncrawler-release-f
- ROI: 8
- Finding: DC-RF-01 (MEDIUM) from code review

## Task
Fix schema hook pairing gap for the `feature_id` column on `dc_requirements`.

## Background
Commit `ca45aa0e0` added `feature_id VARCHAR(64) NOT NULL DEFAULT ''` to the
`dc_requirements` table via direct SQL `ALTER TABLE` applied to production.
The column is referenced by the new `roadmap-set-feature` drush command, but:
1. The `dc_requirements` table creation helper in `dungeoncrawler_content.install` does NOT include `feature_id`.
2. No `hook_update_N` exists to add the column via `drush updatedb`.

This means fresh dev environments and any existing install that hasn't had the raw SQL applied will fail when running the drush command (unknown column error).

## Acceptance criteria
1. Add `feature_id` to the `dc_requirements` table-creation schema helper in
   `dungeoncrawler_content.install` so fresh installs include the column.
2. Add `dungeoncrawler_content_update_10037()` that calls `addField` with an
   idempotency guard (`fieldExists` before adding).
   - Column spec: `varchar`, length 64, `not null` TRUE, default `''`.
   - Add an index `idx_feature_id` on the column (or confirm it exists).
3. Verify with: `drush updatedb --no` shows the new update hook, then apply it.

## File
`sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.install`
