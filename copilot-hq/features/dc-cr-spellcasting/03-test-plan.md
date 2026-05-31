# Test Plan: dc-cr-spellcasting

## Coverage summary
- AC items: ~18 (spell slots, traditions, prepared/spontaneous, attack/DC, heightening, cantrips, focus spells, data model)
- Test cases: 14 (TC-SPC-01–14)
- Suites: playwright (character creation, encounter)
- Security: AC exemption granted

---

## TC-SPC-01 — Spell slot display keyed by level (1–10)
- Description: Spellcasting character shows spell slots keyed by level per class progression table
- Suite: playwright/character-creation
- Expected: spell_slots displayed per level 1–10; counts match class table
- AC: AC-001

## TC-SPC-02 — Prepared caster: slot decrement and daily restore
- Description: Prepared caster spending a slot decrements that level; rest restores all slots to max
- Suite: playwright/encounter
- Expected: cast → slot_level decremented by 1; after rest → all slots at max
- AC: AC-001

## TC-SPC-03 — Spontaneous caster: can upcast in any available slot
- Description: Spontaneous caster may cast a known spell at a higher slot level; slot of target level decremented
- Suite: playwright/encounter
- Expected: spontaneous caster can choose any slot ≥ spell level; correct slot level decremented
- AC: AC-001

## TC-SPC-04 — Spellcasting tradition: four valid values, tradition-gated spell access
- Description: Character tradition field = one of {arcane, divine, occult, primal}; spell tradition check enforced on cast
- Suite: playwright/character-creation
- Expected: tradition field shows four options; attempting to cast off-tradition spell = blocked
- AC: AC-002

## TC-SPC-05 — Prepared caster: today's preparation separate from known spells
- Description: Prepared caster's spell list shows prepared-for-today slots distinct from full known spell list
- Suite: playwright/character-creation
- Expected: preparation UI shows daily prepared selection; known spells list separate; unprepared spell blocked
- AC: AC-003

## TC-SPC-06 — Spell attack roll and DC calculation
- Description: Spell attack = d20 + proficiency + key ability + item bonuses; Spell DC = 10 + proficiency + key ability
- Suite: playwright/encounter
- Expected: spell attack roll uses correct formula; DC updates when proficiency rank or key ability changes
- AC: AC-004

## TC-SPC-07 — Heightened spells: heighten entries apply at higher slot level
- Description: Spell cast at higher than base level applies heightened effects; signature spells auto-heighten for spontaneous casters
- Suite: playwright/encounter
- Expected: heighten_entries applied at correct spell level; spontaneous signature spells heighten automatically; prepared caster must explicitly prepare in higher slot
- AC: AC-005

## TC-SPC-08 — Cantrips: no slot cost, auto-heighten to highest spell level
- Description: Cantrips cast without spending slots; effective level = character's highest spell level
- Suite: playwright/encounter
- Expected: cantrip cast → no slot change; cantrip.effective_level updates when character's max spell level increases
- AC: AC-006

## TC-SPC-09 — Focus spells: spend Focus Point (not spell slot); Refocus restores 1 FP
- Description: Focus spell costs 1 FP from focus pool; Refocus 10-min action restores 1 FP (max 3)
- Suite: playwright/encounter
- Expected: focus spell cast → fp –1; spell_slot unchanged; Refocus → fp +1 up to max
- AC: AC-007

## TC-SPC-10 — Data model: spell_slots, tradition, casting_type, attack modifier, dc, focus pool
- Description: Character entity has all required spellcasting fields; spell content type has required fields
- Suite: playwright/character-creation
- Expected: all field names and types match spec; spell content type has traditions[], heighten_entries[], is_cantrip, save_type, requires_attack_roll
- AC: AC-008

## TC-SPC-11 — Prepared caster cannot cast unprepared spell
- Description: Prepared caster who has not prepared a spell is blocked from casting it
- Suite: playwright/encounter
- Expected: unprepared spell attempt → rejection with clear feedback
- AC: AC-003

## TC-SPC-12 — Proficiency rank change updates spell attack and DC
- Description: When character proficiency rank advances, spell_attack_modifier and spell_dc recalculate
- Suite: playwright/character-creation
- Expected: rank change event triggers stat recalculation; new values reflect updated proficiency
- AC: AC-004

## TC-SPC-13 — Four traditions present and correct spell lists
- Description: Each tradition (arcane/divine/occult/primal) has distinct spell lists
- Suite: playwright/character-creation
- Expected: spell selector filters by tradition; spells tagged with one or more traditions
- AC: AC-002

## TC-SPC-14 — Focus pool maximum is 3; cap enforced
- Description: Focus pool cannot exceed 3 regardless of sources
- Suite: playwright/character-creation
- Expected: adding fourth focus source does not increase fp_max beyond 3
- AC: AC-007
