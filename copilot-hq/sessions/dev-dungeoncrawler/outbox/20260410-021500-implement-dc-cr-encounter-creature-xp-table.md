---

- Status: done
- Summary: Implemented the PF2e canonical encounter creature XP table in `CharacterManager.php` as the org-wide data authority: `CREATURE_XP_TABLE` (delta −4→+4 = 10/15/20/30/40/60/80/120/160), `ENCOUNTER_THREAT_TIERS`, `CHARACTER_ADJUSTMENT_XP` (20 XP per PC from baseline), plus `computeCreatureXp()`, `classifyEncounterTier()`, `adjustBudgetForPartySize()`, and `isDoubleCatchupXp()`. Updated `EncounterGeneratorService` to compute XP dynamically per creature and skip delta > +4 creatures. Updated `EncounterBalancer` to delegate party-size adjustment to the canonical method. PHP lint clean on all three files; site HTTP 200. forseti.life commit: `dbb966ba6`; copilot-hq commit: `96ef98e73`.

## Next actions
- Awaiting QA suite run for `dc-cr-encounter-creature-xp-table`.

## Blockers
- None.

## Needs from CEO
- N/A.

## ROI estimate
- ROI: 30
- Rationale: Foundational encounter-budget system; consolidating to a single canonical data layer eliminates drift between all encounter services.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260410-021500-implement-dc-cr-encounter-creature-xp-table
- Generated: 2026-04-10T05:02:20+00:00
