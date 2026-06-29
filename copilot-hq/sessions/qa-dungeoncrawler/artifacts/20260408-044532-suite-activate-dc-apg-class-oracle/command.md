# Suite Activation: dc-apg-class-oracle

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-08T04:45:32+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-apg-class-oracle"`**  
   This links the test to the living requirements doc at `features/dc-apg-class-oracle/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-apg-class-oracle-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-apg-class-oracle",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-apg-class-oracle"`**  
   Example:
   ```json
   {
     "id": "dc-apg-class-oracle-<route-slug>",
     "feature_id": "dc-apg-class-oracle",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-apg-class-oracle",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-apg-class-oracle

## Coverage summary
- AC items: ~28 (class record, mystery, revelation spells, curse, integration, edge cases)
- Test cases: 17 (TC-ORC-01–17)
- Suites: playwright (character creation, encounter)
- Security: AC exemption granted (no new routes)

---

## TC-ORC-01 — Class record and spellcasting
- Description: HP 8+Con/level; key ability Cha; Expert Will; Trained Fortitude/Reflex; divine spontaneous spellcasting; all material components → somatic; 5 cantrips + 2 1st-level spells
- Suite: playwright/character-creation
- Expected: stats correct; material_component_required = false for all oracle spells; starts with 5 cantrips + 2 spells
- AC: Fundamentals-1–5

## TC-ORC-02 — Cantrips auto-heighten; spell slots follow sorcerer progression
- Description: Cantrips heighten to half class level rounded up; spell slots per day match sorcerer progression
- Suite: playwright/character-creation
- Expected: cantrip.effective_level = ceil(level / 2); spell_slots match TABLE 2-3
- AC: Fundamentals-6–7

## TC-ORC-03 — Signature Spells at L3
- Description: At L3, one spell per accessible spell level can be cast at any available level
- Suite: playwright/character-creation
- Expected: at L3, one signature_spell slot per spell level; selected spell castable at any higher slot
- AC: Fundamentals-8

## TC-ORC-04 — Mystery selection: fixed at creation, 8 options
- Description: Oracle selects one mystery at creation; cannot change; 8 options available
- Suite: playwright/character-creation
- Expected: mystery selector shows 8 options; after selection, mystery is locked; change_mystery = blocked
- AC: Mystery-1–2

## TC-ORC-05 — Revelation spells: cursebound trait and focus pool
- Description: All revelation spells have cursebound trait; focus pool starts at 2 FP; Refocus = 10 min = +1 FP
- Suite: playwright/character-creation
- Expected: oracle.focus_pool_start = 2; all revelation spells have cursebound = true; refocus restores 1 FP
- AC: Revelation-1–4

## TC-ORC-06 — Revelation spells at L1: initial + domain choice
- Description: Oracle learns exactly 2 revelation spells at L1 — mystery's initial (fixed, no choice) + one domain spell (player choice)
- Suite: playwright/character-creation
- Expected: first revelation spell is forced (mystery-specific); second is player choice from domain list
- AC: Revelation-4, Edge-3

## TC-ORC-07 — Curse stage machine: 4 stages
- Description: Stage 0 (basic, passive/constant) → minor → moderate → overwhelmed; casting revelation spell advances by 1
- Suite: playwright/encounter
- Expected: initial curse_stage = 0 (basic); each revelation cast advances by 1; at moderate → overwhelmed triggers; overwhelmed blocks all revelation casting
- AC: Curse-1–5, Integration-2

## TC-ORC-08 — Curse overwhelmed state
- Description: Overwhelmed: cannot cast or sustain revelation spells until next daily prep
- Suite: playwright/encounter
- Expected: at overwhelmed: revelation_cast = blocked; sustain_revelation = blocked; resets at daily_prep
- AC: Curse-5

## TC-ORC-09 — Curse refocus: moderate → minor reset
- Description: Refocusing at moderate or higher: curse resets to minor (not basic) + restores 1 FP
- Suite: playwright/encounter
- Expected: refocus_at_moderate → curse_stage = minor; FP += 1; not reset to basic
- AC: Curse-6, Edge-4

## TC-ORC-10 — Curse daily reset to basic
- Description: 8 hours rest + daily preparations resets curse to basic stage
- Suite: playwright/encounter
- Expected: after daily_prep: curse_stage = basic
- AC: Curse-7

## TC-ORC-11 — Curse irremovable
- Description: Oracular curse cannot be removed, mitigated, or suppressed by any spell or item (remove curse has no effect)
- Suite: playwright/encounter
- Expected: remove_curse targeting oracle returns failure; curse_stage unchanged
- AC: Curse-9, Edge-2

## TC-ORC-12 — Curse traits: curse, divine, necromancy
- Description: Oracle curse has curse, divine, and necromancy traits
- Suite: playwright/character-creation
- Expected: curse.traits includes [curse, divine, necromancy]
- AC: Curse-8

## TC-ORC-13 — Per-mystery curse progressions: unique effects
- Description: All 8 mysteries define unique 4-stage curse progressions (not a shared generic widget)
- Suite: playwright/character-creation
- Expected: each of 8 mysteries has distinct curse_stage effects in data; UI renders them uniquely
- AC: Curse-3, MysterySpells-1, Integration-1

## TC-ORC-14 — Per-mystery granted content
- Description: Each mystery provides: initial revelation spell, advanced revelation spell, greater revelation spell, domain spell choices
- Suite: playwright/character-creation
- Expected: all 8 mysteries have complete spell sets; initial revelation always locked; advanced/greater accessible at correct levels
- AC: MysterySpells-1–8

## TC-ORC-15 — Focus pool cap: stays at 2 unless feats expand it
- Description: Oracle focus pool does not auto-grow beyond 2 unless a feat explicitly adds focus spells
- Suite: playwright/character-creation
- Expected: oracle.focus_pool_max = 2 (standard cap); grows only when focus_spell feats taken; max cap at 3 per standard rules
- AC: Integration-4

## TC-ORC-16 — Edge: attempt revelation spell with 0 FP
- Description: Attempt to cast revelation spell at 0 FP → blocked even if curse stage allows it
- Suite: playwright/encounter
- Expected: revelation_cast blocked when focus_points = 0 regardless of curse_stage
- AC: Edge-1

## TC-ORC-17 — Edge: oracle blocked before first revelation cast of the day
- Description: Oracle cannot cast cursebound spells if curse has not been activated today (no curse active yet)
- Suite: playwright/encounter
- Expected: At session start, if no cursebound spell cast today, first revelation spell activates curse at basic; this is already the initial state — verify initial casting path succeeds
- AC: Revelation (initial cast note)

### Acceptance criteria (reference)

# Acceptance Criteria: Oracle Class Mechanics (APG)

## Feature: dc-apg-class-oracle
## Source: PF2E Advanced Player's Guide, Chapter 2

---

## Class Fundamentals

- [ ] Class record: HP 8 + Con per level, key ability Charisma
- [ ] Starting saves: Expert Will, Trained Fortitude, Trained Reflex
- [ ] Divine spontaneous spellcasting using Charisma modifier for spell DC and spell attack
- [ ] All material components replaced by somatic components for oracle spellcasting
- [ ] Starts with 5 cantrips + 2 first-level spells in repertoire
- [ ] Cantrips auto-heighten to half class level rounded up
- [ ] Spells per day follow sorcerer progression (TABLE 2-3)
- [ ] Signature Spells at L3: one spell per accessible spell level can be cast at any available level

---

## Mystery Selection

- [ ] Oracle selects one mystery at character creation; cannot change
- [ ] Mysteries: Ancestors, Battle, Bones, Cosmos, Flames, Life, Lore, Tempest
- [ ] Each mystery grants: unique domain spell options, revelation spells, oracular curse progression

---

## Revelation Spells and Cursebound

- [ ] Revelation spells are focus spells with the cursebound trait
- [ ] Casting any cursebound spell advances the oracular curse one stage
- [ ] Oracle focus pool starts at 2 Focus Points (not 1)
- [ ] Refocus: 10 minutes, restores 1 Focus Point
- [ ] Oracle learns exactly 2 revelation spells at L1: the mystery's initial revelation spell (fixed) + one from associated domains (player's choice)
- [ ] Oracle cannot cast cursebound spells if curse has not been activated today (no curse active yet before first casting)
- [ ] Cursebound spells have divine, focus, and cursebound traits

---

## Oracular Curse (4-Stage Progression)

- [ ] Stage 0 (basic): passive/constant effect from character creation — always active
- [ ] Casting any revelation spell at basic → advances to minor
- [ ] Casting any revelation spell at minor → advances to moderate
- [ ] Casting any revelation spell at moderate → triggers **overwhelmed** condition
- [ ] Overwhelmed: cannot cast or sustain any revelation spell until next daily preparations
- [ ] Refocusing while at moderate or higher: reduces curse to minor + restores 1 Focus Point
- [ ] Resting 8 hours + daily preparations: returns curse to basic
- [ ] Curse has the curse, divine, and necromancy traits
- [ ] Curse **cannot** be removed, mitigated, or suppressed by any spell or item (e.g., remove curse has no effect)
- [ ] At higher levels (system unlock), major and extreme curse stages become accessible before overwhelmed triggers

---

## Mystery Curses and Spells (per mystery)

- [ ] Each mystery defines its own 4-stage curse progression (unique effects per mystery, not generic conditions)
- [ ] Each mystery provides: initial revelation spell, advanced revelation spell, greater revelation spell, domain spell choices
- [ ] Ancestors mystery: curse involves ancestral whispers, potential confusion effect
- [ ] Battle mystery: curse involves a war-scarred body, physical combat-related penalties/bonuses
- [ ] Bones mystery: curse involves undead resonance, effects tied to vitality/void energy
- [ ] Cosmos mystery: curse involves cosmic connection, gravity or light disturbances
- [ ] Flames mystery: curse involves fire and light emissions, fire vulnerability
- [ ] Life mystery: curse involves vital overflow, positive energy emissions
- [ ] Lore mystery: curse involves information overload, mental strain
- [ ] Tempest mystery: curse involves storm and weather manifestations

---

## Integration Checks

- [ ] Curse stage tracker persists across turns within a day; resets on daily prep
- [ ] Each unique mystery curse has a distinct UI representation (not a single shared widget)
- [ ] Overwhelmed state prevents revelation spell slots from being used even if Focus Points remain
- [ ] Focus pool cap remains 2 for oracle unless feats expand it
- [ ] Signature spells at L3: player selects one spell per spell level from repertoire; those spells become flexible-height castable

## Edge Cases

- [ ] Oracle with 0 FP but curse at minor attempting to cast revelation spell: blocked (no FP available)
- [ ] Remove curse targeting an oracle: fails silently (curse is class feature, not a removable affliction)
- [ ] Two revelation spells at L1: first is always mystery's initial revelation (no choice), second is domain spell choice
- [ ] When refocusing at moderate, curse resets to minor (not basic) — verify state machine
- Agent: qa-dungeoncrawler
- Status: pending
