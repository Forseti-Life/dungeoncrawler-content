# Implementation Notes — dc-cr-spellcasting

Commit: `502292a4f`

## Files changed

### EncounterPhaseHandler.php — `processCastSpell()`
Replaced stub with full implementation:
- Tradition validation: checks `entity_ref['spellcasting_tradition']` matches `params['tradition']`
- Cantrip path: no slot consumed; effective level = highest slot level with `max > 0`
- Focus spell path: deducts 1 from `entity_ref['focus_points']`; errors if 0 FP
- Prepared caster: validates `prepared_spells[$level]` contains `spell_id`; deducts slot
- Spontaneous caster: validates `spell_slots[$level]['used'] < max`; deducts slot
- Spell attack roll: d20 + `spell_attack_modifier` vs target AC; returns degree of success
- Spell DC: always returned in result for saving throws

### ExplorationPhaseHandler.php
**`processCastSpell()`** — same logic as encounter handler but uses entity-in-array pattern:
- `entity['stats']['spellcasting_tradition']`, `entity['stats']['casting_type']`
- `entity['state']['spell_slots']`, `entity['state']['prepared_spells']`, `entity['state']['focus_points']`

**`getLegalIntents()`** — added `refocus`, `prepare_spell`

**`processIntent()` new cases:**
- `refocus`: restores 1 FP (max = `focus_points_max`); tags 10 min elapsed
- `prepare_spell`: prepared casters store individual spell to slot; spontaneous casters get error

**`processRest()`** — long rest now:
- Zeroes all `spell_slots[*]['used']` counts
- Restores `focus_points` to max

**`processDailyPrepare()`** — daily prep now:
- Zeroes all `spell_slots[*]['used']` counts (AC-001)
- Restores `focus_points` to max
- Stores `params['prepared_spells']` → `entity['state']['prepared_spells']` for prepared casters (AC-003)
- Marks `daily_abilities` ready

## Data model used
```
entity_ref / entity['state']:
  spell_slots: {'1': {'max': 2, 'used': 0}, ...}
  prepared_spells: {'1': ['magic_missile', 'shield'], ...}
  focus_points: 2

entity['stats']:
  spellcasting_tradition: arcane|divine|occult|primal
  casting_type: prepared|spontaneous
  spell_attack_modifier: int
  spell_dc: int
  focus_points_max: int
```

## AC coverage
- AC-001: Spell slots tracked per level, restored on daily prepare and long rest ✓
- AC-002: Tradition validation ✓
- AC-003: Prepared vs spontaneous slot management ✓
- AC-004: Spell attack roll and DC ✓
- AC-005: Heightening — slot level passed through in result for caller to apply ✓
- AC-006: Cantrips scale to highest slot level, no slot consumed ✓
- AC-007: Focus spells consume FP, refocus restores 1 ✓
- AC-008: Data model fields documented and implemented ✓
