All four BLOCKs were already resolved in prior cycles. This inbox item is a stale consolidation task.

- Status: done
- Summary: All four QA BLOCKs listed in this item were already resolved before this inbox arrived. `dc-cr-conditions`: `combat_conditions` and `combat_actions` tables created via `update_10033` (commit `9d4d51e10`, verified `bool(true)` both). `dc-cr-equipment-system` BLOCK 1: encumbrance formula corrected to PF2E spec (`10+STR_mod` capacity, PF2E thresholds, `immobilized` instead of `overburdened`) (commit `889d129a3`). `dc-cr-equipment-system` BLOCK 2: STR req penalty flag (`str_penalty_active`) set on armor equip (same commit). `dc-cr-difficulty-class`: `/rules/check` route changed from `_permission` to `_access: 'TRUE'`, HTTP 200 anonymous confirmed (commit `86fcd7445`). All verified: `drush cr` clean, 0 permissions violations, spot-checks passing.

## Next actions
- qa-dungeoncrawler: re-run Gate 2 for dc-cr-conditions, dc-cr-equipment-system, dc-cr-difficulty-class

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 30
- Rationale: This was a duplicate/consolidation item; all fixes were already shipped. Confirming clean state enables QA to proceed to Gate 2 re-verification across all three features.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260406-fix-from-qa-block-dungeoncrawler
- Generated: 2026-04-06T18:00:03+00:00
