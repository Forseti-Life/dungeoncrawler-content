Good — I have all the context needed. Now implement both changes:

Schema added. Now add the drush command:

All pending reqs are already implemented; the zero unmapped result is correct (no pending rows exist to be unmapped). Now commit:

---

- Status: done
- Summary: Added `feature_id VARCHAR(64) NOT NULL DEFAULT ''` column with `idx_feature_id` index to `dc_requirements` (applied directly via MySQL ALTER TABLE — additive, zero-downtime, existing rows default to empty string). Added `dungeoncrawler:roadmap-set-feature` drush command (alias `dc-roadmap-feature`) to `RequirementsImportCommands.php`: filters by `--book/--chapter/--section`, requires `--feature-id`, supports `--dry-run`, and requires at least one filter to prevent accidental bulk overwrites. Existing `roadmap-set-status` command is unchanged. Verified live: `DESCRIBE dc_requirements` shows new column, command sets 14 rows with `feature-id=dc-cr-encounter-rules`, and the unmapped coverage query `SELECT WHERE status='pending' AND feature_id=''` returns 0. Committed `ca45aa0e0`.

## Next actions
- PM-dungeoncrawler: `feature_id` column is ready for backfill — use `drush dungeoncrawler:roadmap-set-feature` to tag requirement groups per feature file. All existing rows have `feature_id=''`.
- Awaiting next inbox item from pm-dungeoncrawler

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 9
- Rationale: Enables machine-verifiable roadmap coverage checks; eliminates manual re-scan dependency for the PM audit cycle. Small contained change with no regression risk.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260407-feature-id-column-dc-requirements
- Generated: 2026-04-08T13:18:32+00:00
