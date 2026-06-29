# Implementation Notes: dc-cr-skills-recall-knowledge

**Dev:** dev-dungeoncrawler
**Date:** 2026-04-08

## KB reference
None found specific to secret check result obfuscation in prior DungeonCrawler KB. RecallKnowledgeService and IdentifyMagicService were pre-built in a prior session (dc-cr-dc-rarity-spell-adjustment dependency work).

## What was built

### EncounterPhaseHandler.php
- Added `recall_knowledge` to `getLegalIntents()` (REQ 1591).
- Added `recall_knowledge` to `getActionCost()` → cost = 1 (REQ 1591).
- Added `recall_knowledge` case to `processIntent()`:
  - Accepts `params['dc']` (explicit) or computes via `RecallKnowledgeService::computeDc()` using `subject_type`, `level`, `rarity`, `spell_rank`, `availability`.
  - Rolls d20 + `skill_bonus`, computes degree via `combatCalculator->calculateDegreeOfSuccess()`.
  - Crit Success: returns `known_info` + `bonus_detail`; player message = "You recall detailed information…".
  - Success: returns `known_info`; player message = "You recall accurate information…".
  - Failure: returns nothing; player message = "You fail to recall anything useful."
  - Crit Failure (REQ 1594): returns `false_info` (if provided); player message = "You recall information about the subject." — indistinguishable from Success to player.
  - Per-character attempt tracking: `$game_state['recall_knowledge_attempts'][$actor_id . ':' . $target_id]` prevents re-attempts until cleared (REQ 2329).
  - `secret: true` flag on result: raw d20 is not exposed to player.

### ExplorationPhaseHandler.php
- Added `recall_knowledge`, `decipher_writing`, `identify_magic`, `learn_a_spell` to `getLegalIntents()`.
- Added `recall_knowledge` case (same logic as encounter but using inline `calculateDegreeOfSuccess()`).
- Added `decipher_writing` case:
  - Accepts `params['dc']` or falls back to `DcAdjustmentService::simpleDc('trained')`.
  - Advances exploration time 10 minutes.
- Added `identify_magic` case (Occultism/Religion tradition routing):
  - Calls `IdentifyMagicService::computeDc()` for level/rarity/spell_rank-based DC.
  - `params['tradition_match'] = false` → adds +5 DC penalty (wrong tradition — not blocked, REQ edge case).
  - Returns `wrong_tradition_penalty` in result for transparency.
- Added `learn_a_spell` case (Occultism/Religion):
  - Accepts `params['dc']` or computes via `DcAdjustmentService::spellLevelDc() + compute()` with rarity.
  - Advances exploration time 480 minutes (8 hours).

## Instantiation pattern
`RecallKnowledgeService` and `IdentifyMagicService` are Drupal services but are NOT injected into either phase handler (would require constructor/services.yml changes). Both are instantiated inline as `new XxxService(new DcAdjustmentService())` — `DcAdjustmentService` has no constructor dependencies, making this safe. If injection is needed in future, add to services.yml and constructor args.

## Files changed
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/EncounterPhaseHandler.php`
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/ExplorationPhaseHandler.php`

## Verification
- PHP lint: `php -l` passes on both files.
- `drush cr`: cache rebuild complete.
- QA suite: `./vendor/bin/phpunit --filter RecallKnowledgeTest --testsuite module-test-suite`

## 2026-04-18 follow-up
- Closed the remaining `TABLE 10-7: Creature Identification Skills` roadmap row by adding focused regression coverage for `CharacterManager::recallKnowledgeSkillsForTraits()`.
- Coverage now locks the PF2e creature-trait routing table for:
  - single-skill groups (`humanoid`/`animal`/`undead`)
  - multi-skill groups (`dragon`/`fey`/`spirit`)
  - GM Lore fallback for unknown traits
  - normalization/deduplication of mixed-case duplicate traits

## Rollback
- Revert commit; run `drush cr`.

## Security checklist
- [x] No new routes added (existing encounter/exploration handler endpoints only).
- [x] No PII logged; only character_id, skill_used, degree logged via GameEventLogger.
- [x] Re-attempt blocking enforced server-side.
- [x] False info on Crit Fail never exposes internal degree to player-facing output.
