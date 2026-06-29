# Test Plan Design: dc-cr-spellcasting

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T16:28:40+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-spellcasting/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-spellcasting "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/dungeoncrawler/suite.json`
- Do NOT edit `org-chart/sites/dungeoncrawler.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

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
- Agent: qa-dungeoncrawler
- Status: pending
