# Suite Activation: dc-cr-human-ancestry

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-08T02:13:20+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-human-ancestry"`**  
   This links the test to the living requirements doc at `features/dc-cr-human-ancestry/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-human-ancestry-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-human-ancestry",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-human-ancestry"`**  
   Example:
   ```json
   {
     "id": "dc-cr-human-ancestry-<route-slug>",
     "feature_id": "dc-cr-human-ancestry",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-human-ancestry",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-human-ancestry

## Coverage summary
- AC items: 18 (14 happy path, 3 edge cases, 2 failure modes) + 1 DB gap task
- Test cases: 18 (TC-HUM-01–18)
- Suites: playwright (character creation flows)
- Security: AC exemption granted (no new routes)
- PM NOTE: core/ch01 and core/ch02 DB rows not yet loaded. Dev DB-load task must complete before this feature reaches implementation. TC-HUM-18 documents this dependency.

---

## TC-HUM-01 — Core stats: HP, size, speed
- Description: Human ancestry grants HP 8, Medium size, Speed 25 ft
- Suite: playwright/character-creation
- Expected: character.hp_from_ancestry = 8; size = Medium; speed = 25
- AC: Stats-1

## TC-HUM-02 — Two free ability boosts (unique scores)
- Description: Player chooses any two different ability scores for boosts; system enforces uniqueness
- Suite: playwright/character-creation
- Expected: exactly 2 free boosts allocated; duplicate selection blocked
- AC: Stats-2, Edge-1, Failure-1

## TC-HUM-03 — No ability flaw
- Description: Human ancestry applies no ability flaw
- Suite: playwright/character-creation
- Expected: no ability_flaw entries on character after Human ancestry selection
- AC: Stats-3

## TC-HUM-04 — Traits
- Description: Human and Humanoid traits applied
- Suite: playwright/character-creation
- Expected: character.traits includes [Human, Humanoid]
- AC: Stats-4

## TC-HUM-05 — Starting languages by Int modifier
- Description: Common granted; each point of positive Int modifier adds one bonus language from Regional Languages list
- Suite: playwright/character-creation
- Expected: character.languages includes Common; bonus_language_slots = max(0, int_modifier); only Regional Languages selectable
- AC: Stats-5, Stats-6

## TC-HUM-06 — Standard vision (no special senses)
- Description: Humans have standard vision by default (not low-light or darkvision)
- Suite: playwright/character-creation
- Expected: character.senses = [standard_vision] only; no low_light_vision or darkvision unless granted by heritage
- AC: Senses-1

## TC-HUM-07 — Feat: Adapted Cantrip (spellcasting gate)
- Description: Grants one cantrip from a different tradition; requires spellcasting class feature
- Suite: playwright/character-creation
- Expected: cantrip added to spell list from a different tradition; feat blocked if no spellcasting class feature
- AC: Feat-1

## TC-HUM-08 — Feat: Cooperative Nature
- Description: +4 circumstance bonus to Aid actions
- Suite: playwright/character-creation
- Expected: aid_action.bonus = +4 circumstance
- AC: Feat-2

## TC-HUM-09 — Feat: General Training
- Description: One bonus non-skill general feat taken immediately
- Suite: playwright/character-creation
- Expected: bonus_feat_slot opens; only non-skill general feats selectable; feat applied immediately
- AC: Feat-3

## TC-HUM-10 — Feat: Haughty Obstinacy
- Description: CritFail vs. coercion/control improves to Failure; if target Will > spell DC, save as normal
- Suite: playwright/character-creation
- Expected: coercion/control crit_fail → outcome = failure; target_will > spell_dc: normal save (no improvement)
- AC: Feat-4

## TC-HUM-11 — Feat: Natural Ambition
- Description: One bonus 1st-level class feat from own class; 2nd-level class feat blocked
- Suite: playwright/character-creation
- Expected: bonus 1st-level class feat slot opens; only own class + level 1 feats selectable; level 2 feat attempt blocked
- AC: Feat-5, Edge-2, Failure-2

## TC-HUM-12 — Feat: Natural Skill
- Description: Trained in two additional skills of choice
- Suite: playwright/character-creation
- Expected: 2 additional skill training slots granted; player may choose any skills
- AC: Feat-6

## TC-HUM-13 — Feat: Unconventional Weaponry
- Description: Trained in one uncommon weapon; can select feats requiring that weapon
- Suite: playwright/character-creation
- Expected: one uncommon weapon proficiency added at trained; associated weapon feats unlocked for that weapon
- AC: Feat-7

## TC-HUM-14 — Heritage: Half-Elf
- Description: Low-light vision; can take elf ancestry feats in addition to human feats
- Suite: playwright/character-creation
- Expected: senses.low_light_vision = true; elf_ancestry_feat_pool accessible
- AC: Heritage-1

## TC-HUM-15 — Heritage: Half-Orc
- Description: Low-light vision; can take orc ancestry feats in addition to human feats
- Suite: playwright/character-creation
- Expected: senses.low_light_vision = true; orc_ancestry_feat_pool accessible
- AC: Heritage-2

## TC-HUM-16 — Heritage: Skilled Heritage
- Description: Trained in one additional skill; expert in one skill at level 5
- Suite: playwright/character-creation
- Expected: +1 skill training at level 1; selected skill advances to expert automatically at level 5
- AC: Heritage-3

## TC-HUM-17 — Heritage: Versatile Heritage
- Description: One additional general feat at 1st level
- Suite: playwright/character-creation
- Expected: extra general feat slot at level 1; any general feat selectable
- AC: Heritage-4

## TC-HUM-18 — DB Gap dependency: core/ch01–ch02 must be loaded
- Description: Validate that dc_requirements contains rows for core/ch01 and core/ch02 before dev implementation starts
- Suite: automation/data-integrity
- Expected: SELECT COUNT(*) FROM dc_requirements WHERE section LIKE 'core/ch01%' > 0 AND section LIKE 'core/ch02%' > 0
- Roles covered: dev-dungeoncrawler (pre-implementation gate)
- PM NOTE (Action Required): DB-load task must be dispatched to dev-dungeoncrawler before this feature enters dev. This TC is a pre-implementation gate, not a functional test.

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-human-ancestry

## Gap analysis reference
- DB sections: core/ch01 and core/ch02 — NOT YET LOADED in dc_requirements.
  See DB Gap Note in feature.md: zero rows for core/ch01 and core/ch02.
  This AC is written from source rules. A DB-load task must be dispatched separately.
- Depends on: dc-cr-ancestry-system ✓, dc-cr-heritage-system ✓, dc-cr-languages ✓, dc-cr-ancestry-feat-schedule

---

## Happy Path

### Core Stats
- [ ] `[NEW]` Human ancestry: HP 8, Medium size, Speed 25 feet.
- [ ] `[NEW]` Ability boosts: two free ability boosts (player chooses any two different ability scores).
- [ ] `[NEW]` No ability flaw.
- [ ] `[NEW]` Traits: Human, Humanoid.
- [ ] `[NEW]` Starting languages: Common + one bonus language per positive Intelligence modifier.
- [ ] `[NEW]` Additional languages from the Regional Languages list available per Int modifier.

### Senses
- [ ] `[NEW]` Humans have standard (not low-light or darkvision) vision by default.

### Ancestry Feats (1st-level Human feats available at character creation)
- [ ] `[NEW]` Adapted Cantrip (1st, spellcasting class required): one cantrip from a different tradition added to spell list.
- [ ] `[NEW]` Cooperative Nature (1st): +4 circumstance bonus to Aid actions.
- [ ] `[NEW]` General Training (1st): one bonus general feat (non-skill general feat) taken immediately.
- [ ] `[NEW]` Haughty Obstinacy (1st): when critically failing vs. coercion/control, improve to failure; if target's Will save is higher than spell DC, save as normal.
- [ ] `[NEW]` Natural Ambition (1st): one bonus 1st-level class feat from own class.
- [ ] `[NEW]` Natural Skill (1st): trained in two additional skills of choice.
- [ ] `[NEW]` Unconventional Weaponry (1st): trained in one uncommon weapon; can select feats that require that weapon.

### Heritage Selection (mandatory at character creation — Half-Elf or Half-Orc or Versatile Heritage)
- [ ] `[NEW]` Half-Elf: gains low-light vision; can take elf ancestry feats in addition to human feats.
- [ ] `[NEW]` Half-Orc: gains low-light vision; can take orc ancestry feats in addition to human feats.
- [ ] `[NEW]` Skilled Heritage: trained in one additional skill; expert in one skill at level 5.
- [ ] `[NEW]` Versatile Heritage: one additional general feat at 1st level.
- [ ] `[NEW]` (Additional human heritages if added in later releases follow same pattern.)

---

## DB Gap Note — Action Required
- [ ] `[NEW]` Dev task dispatched: load core/ch01 (Ancestries) and core/ch02 (Heritages) requirement rows into `dc_requirements` before this feature reaches dev implementation.

---

## Edge Cases
- [ ] `[NEW]` Two free boosts: must be different ability scores (system enforces uniqueness).
- [ ] `[NEW]` Natural Ambition: restricted to 1st-level class feats from own class only.
- [ ] `[NEW]` Half-Elf/Half-Orc heritage: can take both human AND elf/orc ancestry feats — cross-ancestry feat pool enabled.

## Failure Modes
- [ ] `[TEST-ONLY]` Selecting same ability score for both free boosts: blocked.
- [ ] `[TEST-ONLY]` Natural Ambition with a 2nd-level class feat: blocked.

## Security acceptance criteria
- Security AC exemption: game-mechanic ancestry selection; no new routes or user-facing input beyond existing character creation forms
