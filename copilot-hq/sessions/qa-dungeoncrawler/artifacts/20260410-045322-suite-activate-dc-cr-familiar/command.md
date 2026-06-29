- Status: done
- Completed: 2026-04-10T16:48:38Z

# Suite Activation: dc-cr-familiar

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-10T04:53:22+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-familiar"`**  
   This links the test to the living requirements doc at `features/dc-cr-familiar/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-familiar-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-familiar",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-familiar"`**  
   Example:
   ```json
   {
     "id": "dc-cr-familiar-<route-slug>",
     "feature_id": "dc-cr-familiar",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-familiar",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-familiar

## Coverage summary
- AC items: ~16 (content type, daily ability selection, familiar vs. companion distinction, touch spell delivery, class-specific rules)
- Test cases: 10 (TC-FAM-01–10)
- Suites: playwright (character creation, encounter, downtime)
- Security: Daily ability selection is server-validated

---

## TC-FAM-01 — Familiar content type: HP = 5 × level, default land speed 25 ft
- Description: Familiar stores familiar_id, character_id, familiar_type, HP, speed, ability list; HP = 5 × character_level
- Suite: playwright/character-creation
- Expected: familiar.HP = 5 × level; familiar.speed = 25 (land default); HP updates on level-up
- AC: AC-001

## TC-FAM-02 — Daily ability selection up to class-granted maximum
- Description: Each day, character selects familiar abilities up to max; max = 2 base + 1 per relevant class feat
- Suite: playwright/character-creation
- Expected: ability selector shows count up to current max; exceeding max blocked; selections reset each day
- AC: AC-002

## TC-FAM-03 — Familiar ability catalog and prerequisites enforced
- Description: Available abilities: Amphibious, Climber, Darkvision, Fast Movement, Flier, Skilled, Speech, Spellcasting, Tough, others; prerequisites enforced (e.g., Flier requires wings)
- Suite: playwright/character-creation
- Expected: Flier blocked if familiar_has_wings = false; all listed abilities appear in selector; prereq check fires
- AC: AC-002

## TC-FAM-04 — Familiar has no combat stats (no attack/damage)
- Description: Familiar content type has no attack_bonus or damage_entries
- Suite: playwright/character-creation
- Expected: familiar record lacks attack/damage fields; UI does not show attack option for familiar
- AC: AC-003

## TC-FAM-05 — Familiar dies at 0 HP; replaced via 1-week downtime
- Description: Familiar dies at 0 HP (not unconscious like animal companion); replaced with 1 week downtime ritual
- Suite: playwright/encounter
- Expected: familiar.HP = 0 → familiar.state = dead; replacement = 1 week downtime ritual
- AC: AC-003

## TC-FAM-06 — Familiar delivers touch spells
- Description: Familiar can deliver touch-range spells as its action within reach
- Suite: playwright/encounter
- Expected: touch spell + familiar in range → familiar_delivery option offered; resolution = same as caster touching
- AC: AC-004

## TC-FAM-07 — Wizard Arcane Bond: standard familiar rules
- Description: Wizard with Arcane Bond follows standard familiar rules
- Suite: playwright/character-creation
- Expected: Arcane Bond grants familiar with standard ability selection; no deviation from base rules
- AC: AC-005

## TC-FAM-08 — Witch familiar: mandatory, stores prepared spells
- Description: Witch familiar is required (not optional); stores witch's prepared spells as patron's vessel
- Suite: playwright/character-creation
- Expected: witch character must have familiar; familiar.spell_storage = witch's prepared spells; familiar cannot be dismissed
- AC: AC-005

## TC-FAM-09 — Daily ability count: server-validated against allowed maximum
- Description: Server validates daily ability selection count against class-granted max; exceeding max rejected
- Suite: playwright/character-creation
- Expected: API/server rejects ability selection count > max; no client-side bypass possible
- AC: Security AC

## TC-FAM-10 — Familiar Spellcasting ability: stores 1 spell slot in familiar
- Description: Familiar with Spellcasting ability can store and deliver 1 spell slot for caster
- Suite: playwright/encounter
- Expected: Spellcasting ability adds 1 stored slot to familiar; slot usable via familiar delivery
- AC: AC-002 (ability catalog)

### Acceptance criteria (reference)

# Acceptance Criteria: Familiar System
# Feature: dc-cr-familiar

## AC-001: Familiar Content Type
- Given a caster class grants a familiar, when the familiar is created, then it stores: familiar_id, character_id, familiar_type (standard), HP (5 × character level), speed (25 ft land by default), and a list of familiar abilities
- Given the character levels up, when familiar HP is recalculated, then HP = 5 × character level

## AC-002: Familiar Abilities
- Given a character has a familiar, when each day begins, when abilities are assigned, then the character selects familiar abilities up to their class-granted maximum (typically 2 at base, +1 per relevant class feat)
- Given available familiar abilities include: Amphibious, Climber, Darkvision, Fast Movement, Flier, Skilled (skill), Speech, Spellcasting (stores 1 spell slot), Tough, and others, when a character selects, then only available abilities are shown
- Given some familiar abilities have prerequisites (e.g., Flier requires the familiar to have wings), when the selection UI is shown, then prerequisites are enforced

## AC-003: Familiar vs. Animal Companion Distinction
- Given familiars and animal companions are distinct systems, when a character has a familiar, then the familiar has no combat stats (no attack or damage entries)
- Given a familiar is attacked, when damage is applied, then damage resolves against familiar HP; familiar dies at 0 HP
- Given a familiar dies, when recovery begins, when the character uses a weekly ritual, then the familiar can be replaced with 1 week of downtime

## AC-004: Spellcasting Delivery (Touch Spells)
- Given a caster with a familiar, when a spell with range Touch is cast, then the familiar can deliver the spell as its action within its reach
- Given the familiar delivers a touch spell, when it reaches the target, then the spell resolves as if the caster had touched the target

## AC-005: Class-Specific Familiar Rules
- Given a Wizard takes Arcane Bond, when the familiar is granted, then it follows standard familiar rules
- Given a Witch class is active, when the witch creates a familiar, then the familiar is required (not optional) and stores the witch's prepared spells as the "patron's vessel"

## Security acceptance criteria

- Security AC exemption: Familiar data is character-scoped. No PII. Daily ability selection is server-validated to prevent selecting more abilities than the class allows.

## Roadmap section

- Roadmap: Core Rulebook
- Chapter: Chapter 3: Classes (Familiar rules)
- Agent: qa-dungeoncrawler
- Status: pending
