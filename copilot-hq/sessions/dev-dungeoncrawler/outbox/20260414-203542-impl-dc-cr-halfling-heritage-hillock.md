Now I understand the structure. `entity['heritage']` is a top-level key on dungeon entities. I can implement all three changes.

Now I have all I need. Three files to change: `FeatEffectManager.php` (flag), `DowntimePhaseHandler.php` (long rest bonus), `ExplorationPhaseHandler.php` (Treat Wounds snack rider).

Also update the CharacterManager benefit text:

- Status: done
- Summary: Hillock Halfling heritage (dc-cr-halfling-heritage-hillock) is fully implemented in commit `a710ad62b` across four files. (1) `FeatEffectManager.php` — added `case 'hillock':` to the heritage switch setting `hillock_halfling_bonus_healing = TRUE` flag for client display. (2) `DowntimePhaseHandler::processLongRest()` — after computing `$hp_per_rest`, adds `+$level` when `entity['heritage'] === 'hillock'`; the existing starvation/thirst healing-blocked guard still applies to the combined total. (3) `ExplorationPhaseHandler::processTreatWounds()` — inside the entity-state loop, adds `+target_level` to `$healed` when target heritage is `hillock` and `$healed > 0` (success/critical success only; failures where `$healed = 0` are excluded); one function call = one application, so no extra state guard is needed. (4) `CharacterManager.php` — corrected the benefit stub text. All AC items satisfied; no new routes, no schema changes, no double-application risk.

## Next actions
- qa-dungeoncrawler: verify AC against commit `a710ad62b` (Gate 2)
- Verify: Hillock Halfling long rest HP includes `+level` on top of normal `con_mod × level` in DowntimePhaseHandler
- Verify: Treat Wounds on Hillock Halfling returns `healed` value that includes `+level` on success/critical success
- Verify: Non-hillock characters receive no bonus from either path

## Blockers
- None.

## ROI estimate
- ROI: 7
- Rationale: Completes a release-m scope item; implements the two active server-side mechanics (rest bonus + TW snack rider) which are more complex than pure flag heritages, so completing this keeps Halfling ancestry mechanically complete and unblocks Gate 2.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260414-203542-impl-dc-cr-halfling-heritage-hillock
- Generated: 2026-04-17T01:00:51+00:00
