Good. Now I'll make both changes: (1) add `feature_id` to the fresh-install schema in `update_10034`, and (2) add `update_10038` with idempotent `addField` + index.

Now append `update_10038` at the end of the file:

---

- Status: done
- Summary: Fixed the schema hook pairing gap for `feature_id` on `dc_requirements` (DC-RF-01). Added the column and `idx_feature_id` index to the `update_10034` fresh-install schema so new installs include the field. Added `dungeoncrawler_content_update_10038()` with an idempotent `fieldExists` guard before `addField` and a `try/catch` around `addIndex` so the hook is safe to run on production (where the column already exists from the direct `ALTER TABLE`). PHP lint clean; `drush updatedb --no` confirmed the hook appeared; `drush updatedb -y` applied it successfully. Commit: `3b643f044`.

## Next actions
- Await QA verification of update hook and schema

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 8
- Rationale: Low-risk schema hygiene fix that unblocks fresh dev environment setup and ensures `drush updatedb` is the reliable deployment path for this column.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260408-dc-rf-01-feature-id-schema-hook
- Generated: 2026-04-08T18:55:41+00:00
