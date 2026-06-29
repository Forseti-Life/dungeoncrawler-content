# Suite Activation: dc-cr-spellcasting

- Agent: qa-dungeoncrawler
- Status: pending

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-08T13:49:28+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-spellcasting"`**  
   This links the test to the living requirements doc at `features/dc-cr-spellcasting/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-spellcasting-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-spellcasting",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-spellcasting"`**  
   Example:
   ```json
   {
     "id": "dc-cr-spellcasting-<route-slug>",
     "feature_id": "dc-cr-spellcasting",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-spellcasting",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

# Acceptance Criteria: Spellcasting Rules System
# Feature: dc-cr-spellcasting

## AC-001: Spell Slot Tracking by Level
- Given a spellcasting character, when the character is rendered, then spell slots are displayed keyed by spell level (1–10) per their class progression table
- Given a prepared caster spending a slot, when a spell is cast, then the appropriate slot level is decremented
- Given a spontaneous caster, when a spell is cast at a higher level, then a slot of the target level is decremented
- Given a rest is completed, when the character recovers, then all spell slots are restored to maximum

## AC-002: Casting Traditions
- Given a character has a spellcasting tradition field, when displayed, then it reflects one of: arcane, divine, occult, primal
- Given a spell belongs to a specific tradition list, when a character tries to cast it, then the system verifies the character's tradition includes that spell

## AC-003: Prepared vs. Spontaneous Casting
- Given a prepared caster (wizard, cleric, druid, etc.), when their spell list is shown, then it includes the prepared-for-today slot allocations separate from their known spells
- Given a spontaneous caster (sorcerer, bard, oracle), when they cast, then any spell in their repertoire may be cast in any available slot without prior preparation
- Given a prepared caster that has not prepared a spell, when they attempt to cast it, then the cast is rejected with feedback

## AC-004: Spell Attack Rolls and DCs
- Given a spellcasting character, when a spell requiring an attack roll is cast, then the roll is calculated as d20 + proficiency bonus + key ability modifier + item bonuses
- Given a target makes a saving throw against a spell, when the DC is calculated, then it equals 10 + proficiency + key ability modifier
- Given the character's proficiency rank advances, when the stats are recalculated, then spell attack and DC update accordingly

## AC-005: Heightening Spells
- Given a spell has heighten entries, when the spell is cast at a higher level than its base level, then the heightened effects apply
- Given a spontaneous caster casts a signature spell, when cast at any slot level, then the heightened benefits apply automatically
- Given a prepared caster, when they prepare a spell in a higher-level slot, then the heightened effect applies when cast

## AC-006: Cantrips
- Given a character knows cantrips, when cantrips are cast, then they do not expend spell slots
- Given cantrips auto-heighten, when a character's highest spell level increases, then the cantrip's effective level updates to match

## AC-007: Focus Spells Integration
- Given a character has a focus pool, when they cast a focus spell, then 1 Focus Point is spent (not a spell slot)
- Given the Refocus action is taken for 10 minutes, when the action completes, then 1 Focus Point is restored (up to maximum 3)

## AC-008: Data Model
- Character entity has fields: `spell_slots{}` (keyed by level 1–10), `spellcasting_tradition` (enum), `casting_type` (prepared|spontaneous), `spell_attack_modifier` (integer), `spell_dc` (integer), `focus_points` (0–3), `focus_points_max` (0–3)
- Spell content type has fields: `traditions[]`, `spell_level`, `heighten_entries[]`, `is_cantrip`, `save_type`, `requires_attack_roll`

## Security acceptance criteria

- Security AC exemption: No user-generated content; all spell data is static rulebook content. No PII stored. Slot state is session-scoped character data with standard auth protection.

## Roadmap section

- Roadmap: Core Rulebook
- Chapter: Chapter 7: Spells
