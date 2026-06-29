# Suite Activation: dc-cr-animal-companion

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-08T19:45:01+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-animal-companion"`**  
   This links the test to the living requirements doc at `features/dc-cr-animal-companion/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-animal-companion-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-animal-companion",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-animal-companion"`**  
   Example:
   ```json
   {
     "id": "dc-cr-animal-companion-<route-slug>",
     "feature_id": "dc-cr-animal-companion",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-animal-companion",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-animal-companion

## Coverage summary
- AC items: ~15 (content type, advancement, commanding, distinction from familiar, species specifics)
- Test cases: 10 (TC-ANC-01–10)
- Suites: playwright (character creation, encounter)
- Security: AC exemption granted

---

## TC-ANC-01 — Companion content type: all required fields stored
- Description: Companion stores: companion_id, character_id, companion_type, size, speeds, senses, HP, AC, saves, attack entries, advancement_level (young|mature|nimble|savage)
- Suite: playwright/character-creation
- Expected: all fields present in companion record; no null required fields
- AC: AC-001

## TC-ANC-02 — Companion initialized at "young" for eligible classes
- Description: Ranger, Druid, Beastmaster Archetype characters initialize companion at "young" advancement
- Suite: playwright/character-creation
- Expected: companion.advancement_level = "young" at creation; non-eligible classes blocked from companion
- AC: AC-001

## TC-ANC-03 — Advancement: young → mature → nimble or savage
- Description: Class feature advancement moves companion through tiers; Mature tier updates stats per table
- Suite: playwright/character-creation
- Expected: advancement trigger updates level field; Mature recalculates AC, saves, HP, attack modifier, damage
- AC: AC-002

## TC-ANC-04 — Command an Animal: 1 action, DC check, gives companion 2 actions
- Description: Command an Animal = 1 action; success vs. DC 15 (or creature Will DC) → companion takes 2 actions that turn
- Suite: playwright/encounter
- Expected: Command action costs 1 action; success → companion.actions_this_turn = 2
- AC: AC-003

## TC-ANC-05 — No Command: companion repeats last-turn actions (Stride/Strike only)
- Description: If Command not used, companion takes only Stride and/or Strike (repeating previous turn behavior)
- Suite: playwright/encounter
- Expected: no Command this turn → companion action options limited to Stride/Strike per prior turn
- AC: AC-003

## TC-ANC-06 — Companion vs. familiar: companion has full combat stats
- Description: Animal companion has full combat stats (attack bonus, damage, AC, saves); familiar does not
- Suite: playwright/character-creation
- Expected: companion record has attack_bonus, damage_entries, AC, saves; familiar record lacks attack entries
- AC: AC-004

## TC-ANC-07 — Companion at 0 HP: unconscious, not permanently dead
- Description: Companion at 0 HP falls unconscious; death only via GM decision or failed recovery over days
- Suite: playwright/encounter
- Expected: HP = 0 → companion.state = unconscious (not destroyed); permanent death requires explicit flag
- AC: AC-004

## TC-ANC-08 — Species selection sets base stats, size, speed, senses, natural attacks
- Description: Each species (bear, bird, cat, wolf, etc.) defines base stats; selection populates all base fields
- Suite: playwright/character-creation
- Expected: species selection fills size, speed, senses, and natural attack type; fields not manually editable unless advanced
- AC: AC-005

## TC-ANC-09 — Flier companion: aerial movement rules applied
- Description: Companion with Flier movement (eagle, bat) uses aerial movement rules (elevation, plunging strike)
- Suite: playwright/encounter
- Expected: flier companion has fly_speed; elevation tracked; plunging strike available at height
- AC: AC-005

## TC-ANC-10 — Companion cannot Command Animal without line of effect
- Description: Command an Animal requires the character to be able to communicate with companion (within range and no blocking condition)
- Suite: playwright/encounter
- Expected: Command blocked if companion outside communicable range; edge case documented in implementation
- AC: AC-003 (integration note)

### Acceptance criteria (reference)

# Acceptance Criteria: Animal Companion System
# Feature: dc-cr-animal-companion

## AC-001: Animal Companion Content Type
- Given a class grants an animal companion, when the companion is created, when stored, then it includes: companion_id, character_id, companion_type (animal species), size, speeds, senses, HP, AC, saves, attack entries, and advancement level (young|mature|nimble|savage)
- Given a Ranger, Druid, or Beastmaster Archetype character, when they gain a companion, then the companion is initialized at "young" advancement level

## AC-002: Companion Advancement
- Given a character reaches a class feature that advances the companion, when the advancement is applied, then the companion moves from young → mature → nimble or savage based on the chosen specialization
- Given the companion reaches Mature level, when stats are recalculated, then AC, saves, HP, attack modifier, and damage all increase per the Mature Animal Companion table

## AC-003: Commanding the Companion
- Given the companion acts in combat, when the character issues the Command an Animal action (1 action), when successful (DC 15 or DC equal to creature's Will DC), then the companion takes 2 actions on its turn
- Given the Command an Animal action is not used, when the companion's turn begins, then the companion takes only the Stride and/or Strike actions it took last turn (repeating behavior)

## AC-004: Companion vs. Familiar Distinction
- Given an animal companion is active, when queried, then it has full combat stats (attack bonus, damage entries, AC, saves) unlike a familiar
- Given an animal companion takes damage, when HP reaches 0, then the companion falls unconscious; it does not die permanently unless the character decides so or recovery fails over days

## AC-005: Species-Specific Companions
- Given animal companion species are defined (bear, bird, cat, wolf, etc.), when a character selects a species, then the species sets base stats, size, speed, senses, and natural attacks
- Given a companion has Flier movement (eagle, bat), when movement is used in combat, then aerial movement rules (elevation, plunging strike) apply

## Security acceptance criteria

- Security AC exemption: Companion data is character-scoped. No PII. Command resolution and companion stat advancement are server-validated.

## Roadmap section

- Roadmap: Core Rulebook
- Chapter: Chapter 3: Classes (Animal Companion rules)
- Agent: qa-dungeoncrawler
- Status: pending
