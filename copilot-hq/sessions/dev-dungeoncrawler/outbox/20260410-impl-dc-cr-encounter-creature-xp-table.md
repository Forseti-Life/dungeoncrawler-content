# Outbox: dc-cr-encounter-creature-xp-table

- Status: done
- Summary: Implemented the PF2e canonical encounter creature XP table in `CharacterManager.php` as the org-wide data authority: `CREATURE_XP_TABLE` (delta −4→+4 = 10/15/20/30/40/60/80/120/160), `ENCOUNTER_THREAT_TIERS` (Trivial 40/Low 60/Moderate 80/Severe 120/Extreme 160), `CHARACTER_ADJUSTMENT_XP` (20 XP per PC from 4-PC baseline), plus static methods `computeCreatureXp()` (returns 0 for delta < −4, null for delta > +4), `classifyEncounterTier()` (includes `beyond_extreme` label), `adjustBudgetForPartySize()`, and `isDoubleCatchupXp()`. Updated `EncounterGeneratorService.calculateXpBudget()` to use `CHARACTER_ADJUSTMENT_XP`-based party-size adjustment (replacing the prior multiplier approach), and `buildEncounter()` to compute XP dynamically per creature via `computeCreatureXp()` at encounter-generation time, skipping delta > +4 creatures as undefined. Removed all hardcoded `xp_value` fields from creature stubs. Updated `EncounterBalancer.adjustBudgetForPartySize()` to delegate to `CharacterManager::adjustBudgetForPartySize()` for a single canonical formula. PHP lint passed on all three files; site HTTP 200. feature.md Status → done. forseti.life commit: `dbb966ba6`.

## Next actions
- None; awaiting QA suite run for this feature.

## Blockers
- None.

## Needs from CEO
- N/A.

## ROI estimate
- ROI: 30
- Rationale: This is the foundational encounter-budget system that all encounter generation, balancing, and XP award features depend on. Consolidating to a single canonical data layer eliminates future drift between EncounterGeneratorService, EncounterBalancer, and CharacterManager.
