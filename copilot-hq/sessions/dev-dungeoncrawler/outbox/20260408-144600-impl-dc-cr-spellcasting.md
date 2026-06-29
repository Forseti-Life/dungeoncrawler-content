Now let me look at ExplorationPhaseHandler entity data access pattern and find the `findEntityInDungeon` method:

Now I have enough to implement. Let me write all the changes:

Now update ExplorationPhaseHandler — add `refocus` and `prepare_spell` to getLegalIntents:

Now find the `learn_a_spell` case handler to add `prepare_spell` and `refocus` handlers after it:

Now I need to add `persistDungeonData()` helper or check if it already exists, and update `processRest()` and `processDailyPrepare()`. Let me also check for `persistDungeonData`:

The parameter order is `($campaign_id, $dungeon_data)` — need to fix my new code:

Now update `processCastSpell()` in ExplorationPhaseHandler to use entity state, and update `processRest()` for focus point restoration:

Now update `processDailyPrepare()` to restore spell slots from entity state on daily prepare:

Now write implementation notes and outbox:

---

- Status: done
- Summary: Implemented the full PF2e spellcasting system across EncounterPhaseHandler and ExplorationPhaseHandler. `processCastSpell()` in both handlers now covers tradition validation, cantrip scaling (no slot consumed), focus spell FP deduction, prepared-spell slot validation, spontaneous slot tracking, spell attack rolls, and spell DC reporting. Added `refocus` and `prepare_spell` exploration activities. Updated `processRest()` to restore slots and focus on long rest. Updated `processDailyPrepare()` to zero used counts, restore focus points, and load prepared spells from params. PHP lint clean; drush cr success. Commits: `502292a4f` (spellcasting code), `5d74a48af` (implementation notes).

## Next actions
- Await QA dispatch for dc-cr-spellcasting verification pass

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 70
- Rationale: Spellcasting is a core PF2e mechanic used by a significant portion of character builds; without it the calculator is incomplete for caster classes, blocking meaningful playtesting of those archetypes.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260408-144600-impl-dc-cr-spellcasting
- Generated: 2026-04-08T18:51:57+00:00
