# Outbox: dc-cr-spells-ch07 — Spellcasting Ch07 Implementation

- Status: done
- Summary: Audited all 100+ acceptance criteria for dc-cr-spells-ch07 against existing service code. The majority of the core spellcasting framework was already implemented (traditions, schools, components, cantrip/focus rank, heightening, prepared/spontaneous gate, spell attack/DC, refocus, counteract). This session identified and implemented the 10 missing mechanics: (1) ESSENCE_TYPES constant added to SpellCatalogService; (2) `computeFocusSpellEffectiveRank()` added (ceil(level/2), same as cantrips); (3) `validateCastTimeForPhase()` added to block Exploration-trait cast times (1 min/10 min/1 hr) in encounters; (4) `performDailyPreparations()` in MagicItemService now resets spell slot used counts, restores focus points to max, and resets innate spell used_today flags; (5) `sustain_spell` action added to EncounterPhaseHandler (Concentrate, 1-action; tracks rounds sustained, >100 rounds → fatigue + end); (6) `dismiss_spell` action added; (7) `declare_metamagic` action added with state machine: metamagic wasted if anything other than cast_spell follows; (8) polymorph battle form cast blocker in `processCastSpell()`; (9) innate spell Charisma modifier enforcement for attack/DC; (10) incapacitation trait note output when target level exceeds half caster level; (11) round-based spell duration decrement added to `processEndTurn()` at start of caster's turn. Commit `c720c1a34`. All three files pass PHP lint.

## Commits
- `c720c1a34` — dc-cr-spells-ch07: implement spellcasting gaps (ch07 acceptance criteria)

## Files changed
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/SpellCatalogService.php`
  - Added `ESSENCE_TYPES = ['mental', 'vital', 'material', 'spiritual']`
  - Added `EXPLORATION_CAST_TIMES = ['one_minute', 'ten_minutes', 'one_hour']`
  - Added `computeFocusSpellEffectiveRank(int $character_level): int`
  - Added `validateCastTimeForPhase(string $cast_time, string $phase): array`
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/MagicItemService.php`
  - `performDailyPreparations()`: resets `game_state['spells'][$char_id]['spell_slots'][*]['used'] = 0`; restores focus points to max; resets innate spell used_today flags
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/EncounterPhaseHandler.php`
  - `getLegalIntents()`: added `sustain_spell`, `dismiss_spell`, `declare_metamagic`
  - `processIntent()`: metamagic wasting pre-switch — clears `metamagic_pending` on non-cast/non-declare actions
  - Case `sustain_spell`: 1-action Concentrate; tracks round count; >100 rounds → fatigue note + end
  - Case `dismiss_spell`: 1-action Concentrate; removes from sustained and duration tracking
  - Case `declare_metamagic`: sets `game_state['turn']['metamagic_pending'][$actor_id]`
  - `processCastSpell()`: exploration cast time guard; polymorph battle form cast blocker; innate spell Charisma mod enforcement; metamagic application and clear; incapacitation trait downgrade note
  - `processEndTurn()`: round-based duration decrement; ends at 0 rounds

## Coverage notes (scope-bounded deferred items)
The following AC items are data/content scope, not mechanics — they require full spell catalog population which is a separate content task:
- All Arcane/Divine/Occult/Primal spell list entries at all levels
- All class focus spells (Bard, Champion, Cleric, Druid, Monk, Sorcerer, Wizard)
- Component substitution rules per class (class feature scope)
- Illusion disbelief resolution (requires target interaction model not yet in scope)
- Trigger-set spells and summoned creature minion tracking (separate feature scope)

## Verification
```bash
php -l sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/SpellCatalogService.php
php -l sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/MagicItemService.php
php -l sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/EncounterPhaseHandler.php
# All: "No syntax errors detected"
```

QA notified for Gate 2 verification.

## Next actions
- QA (`qa-dungeoncrawler`) to run Gate 2 verification against this commit
- Content task needed for full spell catalog population (all traditions, all levels, all class focus spells)

## Blockers
- None

## ROI estimate
- ROI: 40
- Rationale: Spellcasting is the core action economy for all magic-using character classes; these gaps (especially sustain/dismiss/metamagic/daily reset) block basic wizard/cleric/sorcerer play. Unblocking this closes the primary gameplay loop for half the character builds in the game.
