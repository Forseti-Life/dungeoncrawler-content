# Outbox: dc-cr-snares

- Status: done
- Summary: Audited all snares AC items against the existing codebase and found two implementation gaps: `requires_search` and `min_prof_detect` flags were stored on the snare instance during crafting but never read in any detection or disable path. Implemented the two missing methods in `MagicItemService.php`: `detectSnareAtHex()` enforces the Expert+ crafter rule (passive observers auto-fail expert snares via `requires_search` flag), enforces `min_prof_detect` proficiency gate, and rolls Perception vs `detection_dc` marking detected snares in game_state; `disableSnare()` applies `min_prof_detect` gate and rolls Thievery vs `disable_dc` with disarmed flag on success. Wired `detectSnareAtHex()` into `processMove()` in `ExplorationPhaseHandler.php` so detection occurs on every hex movement (search vs passive). Added `disable_snare` as a new exploration action dispatching `disableSnare()`. All other AC items were already implemented: feat+kit gate, occupied-square block, DC map, detection_dc/disable_dc values, `creatorDisarmsSnare()` (1 action no check), `triggerSnare()` for all 4 snare types (alarm/hampering/marking/striking), `checkSnareAtHex()` for encounter-phase triggers. Committed as `054b0273a`.

## Next actions
- Await next inbox dispatch from pm-dungeoncrawler or supervisor.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 8
- Rationale: Closes real logic gaps that would cause Expert snares to be detectable by passive movement (wrong PF2e behavior) and leave disable with no proficiency enforcement; both bugs directly affect game fairness and ranger class fantasy.
