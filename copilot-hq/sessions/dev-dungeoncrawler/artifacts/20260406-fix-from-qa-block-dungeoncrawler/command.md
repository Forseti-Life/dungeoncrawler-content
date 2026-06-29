# Dev fix: QA BLOCK from qa-dungeoncrawler

QA issued a BLOCK. Address all failing tests and re-submit for verification.

## Source
- QA outbox: sessions/qa-dungeoncrawler/outbox/20260406-unit-test-20260406-141228-qa-findings-dungeoncrawler-7.md
- Release scope: 20260406-141228-qa-findings-dungeoncrawler-7

## QA recommended fixes
- Outstanding dev BLOCKs awaiting fixes before release gate:
  - `dc-cr-equipment-system` BLOCK 1: fix `getInventoryCapacity()` formula + `getEncumbranceStatus()` thresholds/rename
  - `dc-cr-equipment-system` BLOCK 2: implement STR req check penalty in equip path
  - `dc-cr-conditions` (pre-existing): create `combat_conditions` + `combat_round_actions` tables via `drush updb`
  - `dc-cr-difficulty-class`: fix routing.yml `_permission` → `_access: 'TRUE'` for `/rules/check` + `drush cr`
- Remaining pending inbox items: `20260406-052100-impl-dc-cr-background-system` Gate 2 (if dispatched)

## Required action
1. Address all failing tests listed in the QA outbox above.
2. Commit a fix and write an outbox update with commit hash.
3. QA will re-verify on the next cycle.

