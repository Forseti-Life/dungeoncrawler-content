# Suite Activation: dc-apg-class-swashbuckler

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-08T04:45:31+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-apg-class-swashbuckler"`**  
   This links the test to the living requirements doc at `features/dc-apg-class-swashbuckler/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-apg-class-swashbuckler-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-apg-class-swashbuckler",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-apg-class-swashbuckler"`**  
   Example:
   ```json
   {
     "id": "dc-apg-class-swashbuckler-<route-slug>",
     "feature_id": "dc-apg-class-swashbuckler",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-apg-class-swashbuckler",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-apg-class-swashbuckler

## Coverage summary
- AC items: ~28 (class record, panache, styles, precise strike, finishers, key abilities, integration, edge cases)
- Test cases: 16 (TC-SWS-01–16)
- Suites: playwright (character creation, encounter)
- Security: AC exemption granted (no new routes)

---

## TC-SWS-01 — Class record and starting proficiencies
- Description: HP 10+Con/level; key ability Dex; Trained Fortitude (upgrades L3); Expert Reflex/Will; trained simple+martial weapons, light armor; Swashbuckler class DC tracked
- Suite: playwright/character-creation
- Expected: stats correct; fortitude upgrades at L3; class_dc tracked at trained
- AC: Fundamentals-1–4

## TC-SWS-02 — Panache: binary state and speed bonus
- Description: Panache is binary (in/out); grants +5-ft status speed bonus; persists until encounter ends or Finisher used
- Suite: playwright/encounter
- Expected: panache = true → speed_bonus = +5 status; panache lost immediately when Finisher performed (before outcome)
- AC: Panache-1–3, Panache-7

## TC-SWS-03 — Panache: check bonus while active
- Description: Panache grants +1 circumstance to checks that would earn panache per selected style
- Suite: playwright/encounter
- Expected: while panache active, style skill check bonus = +1 circumstance
- AC: Panache-4

## TC-SWS-04 — Panache: earned by style's associated skill check success
- Description: Panache earned by succeeding at the style-specific skill check
- Suite: playwright/encounter
- Expected: style skill check success → panache = true; fail → no change
- AC: Panache-5

## TC-SWS-05 — Panache: GM award for very hard non-standard actions
- Description: GM may award panache for particularly daring non-standard actions vs Very Hard DC
- Suite: playwright/encounter
- Expected: GM-award panache mechanism exists (admin tool or GM confirmation); no auto-grant
- AC: Panache-6

## TC-SWS-06 — Styles: correct skill routing and feat grants
- Description: Battledancer (Fascinating Performance + Performance vs. Will), Braggart (Demoralize), Fencer (Feint/Create Diversion), Gymnast (Grapple/Shove/Trip), Wit (Bon Mot + Bon Mot feat)
- Suite: playwright/character-creation
- Expected: each style grants Trained in its skill; correct panache trigger linked; Battledancer grants Fascinating Performance; Wit grants Bon Mot
- AC: Styles-1–5

## TC-SWS-07 — Precise Strike: non-Finisher flat precision bonus
- Description: With panache + qualifying weapon: flat precision +2/+3/+4/+5/+6 at L1/5/9/13/17
- Suite: playwright/encounter
- Expected: non-finisher strike with panache → precision_bonus = flat per level table; requires agile or finesse weapon
- AC: PreciseStrike-1–2

## TC-SWS-08 — Precise Strike: Finisher precision dice
- Description: Finisher strike with panache: 2d6/3d6/4d6/5d6/6d6 precision dice at L1/5/9/13/17
- Suite: playwright/encounter
- Expected: finisher strike → precision_dice = table value; panache consumed before outcome
- AC: PreciseStrike-2

## TC-SWS-09 — Finisher: requires panache and blocks subsequent attack actions
- Description: Finisher requires panache; no additional attack-trait actions that turn after Finisher
- Suite: playwright/encounter
- Expected: Finisher blocked if panache = false; after Finisher, further attack actions that turn = blocked
- AC: Finisher-1–3, Edge-1

## TC-SWS-10 — Finisher: partial damage on failure (not crit fail)
- Description: Some Finishers have a Failure effect (partial damage); Crit Failure does NOT trigger failure effect
- Suite: playwright/encounter
- Expected: finisher.failure → half_damage (flat value); finisher.crit_failure → no failure effect triggered
- AC: Finisher-4

## TC-SWS-11 — Confident Finisher (base L1 Finisher)
- Description: 1-action; success = full Finisher precise strike; failure = half (flat value, not rolled)
- Suite: playwright/encounter
- Expected: success: full precision dice; failure: flat_half_value (no dice); action_cost = 1
- AC: Finisher-5, Edge-2

## TC-SWS-12 — Opportune Riposte (L3 reaction)
- Description: Triggers on foe's critical failure on Strike against swashbuckler; effect: melee Strike or Disarm
- Suite: playwright/encounter
- Expected: on foe_crit_fail_strike → reaction available; player chooses Strike or Disarm
- AC: Riposte-1

## TC-SWS-13 — Vivacious Speed (L3): speed bonus scaling
- Description: Speed bonus with panache: +10/+15/+20/+25/+30 ft at L3/7/11/15/19; without panache: half, rounded to nearest 5 ft
- Suite: playwright/character-creation
- Expected: at L3 with panache: speed_bonus = +10; without panache: +5; scales at later levels
- AC: VSpeed-1–2, Edge-3

## TC-SWS-14 — Exemplary Finisher (L9): style-specific effect on hit
- Description: Activates on Finisher Strike hit; applies style-specific effect
- Suite: playwright/encounter
- Expected: Exemplary Finisher fires only on hit (not miss or failure); effect matches selected style
- AC: ExFin-1, Edge-4

## TC-SWS-15 — Integration: panache state display and auto-toggle on Finisher
- Description: Panache state displayed prominently in encounter UI; toggles automatically when Finisher used
- Suite: playwright/encounter
- Expected: panache indicator visible; Finisher action removes panache immediately (before resolution)
- AC: Integration-1

## TC-SWS-16 — Integration: correct skill for panache generation per style
- Description: Each style's panache trigger uses the correct skill in the encounter phase
- Suite: playwright/encounter
- Expected: Performance (Battledancer), Intimidation (Braggart), Deception (Fencer), Athletics (Gymnast), Diplomacy (Wit) — each style uses only its associated skill for panache generation
- AC: Integration-2

### Acceptance criteria (reference)

# Acceptance Criteria: Swashbuckler Class Mechanics (APG)

## Feature: dc-apg-class-swashbuckler
## Source: PF2E Advanced Player's Guide, Chapter 2

---

## Class Fundamentals

- [ ] Class record: HP 10 + Con per level, key ability Dexterity
- [ ] Saves: Trained Fortitude (upgrades at L3), Expert Reflex, Expert Will
- [ ] Trained in simple and martial weapons, light armor, unarmored defense
- [ ] Swashbuckler class DC tracked separately; starts Trained

---

## Panache

- [ ] Panache is a binary state (in/out); persists until encounter ends or a Finisher is used
- [ ] Panache grants +5-foot status bonus to all movement speeds
- [ ] Panache grants +1 circumstance bonus to checks that would earn panache per selected style
- [ ] Panache enables use of Finisher actions
- [ ] Panache is earned by succeeding at the style's associated skill check
- [ ] GM may award panache for particularly daring non-standard actions vs. Very Hard DC
- [ ] Panache is **lost immediately** when a Finisher is performed (before outcome resolves)

---

## Swashbuckler Styles (Select One at L1)

- [ ] Style grants Trained proficiency in its associated skill
- [ ] **Battledancer**: grants Fascinating Performance feat; panache earned via Performance vs. foe's Will DC
- [ ] **Braggart**: panache earned via successful Demoralize
- [ ] **Fencer**: panache earned via successful Feint or Create a Diversion
- [ ] **Gymnast**: panache earned via successful Grapple, Shove, or Trip
- [ ] **Wit**: grants Bon Mot skill feat; panache earned via successful Bon Mot

---

## Precise Strike

- [ ] Requires panache + qualifying weapon (agile or finesse melee, or agile/finesse unarmed)
- [ ] Non-Finisher Strike bonus (flat precision): +2 (L1), +3 (L5), +4 (L9), +5 (L13), +6 (L17)
- [ ] Finisher Strike bonus (precision dice): 2d6 (L1), 3d6 (L5), 4d6 (L9), 5d6 (L13), 6d6 (L17)

---

## Finisher Actions

- [ ] Finisher actions require panache as prerequisite
- [ ] Panache is consumed immediately on Finisher use (even before outcome)
- [ ] No additional attack-trait actions may be taken that turn after a Finisher
- [ ] Some Finishers have a Failure effect (partial damage); critical failures do **not** trigger failure effects
- [ ] **Confident Finisher** (L1 base Finisher, 1-action): success deals full Finisher precise strike damage; failure deals half (flat value, not rolled)

---

## Opportune Riposte (L3 Reaction)

- [ ] Triggers on a foe's critical failure on a Strike against the swashbuckler
- [ ] Effect choices: melee Strike against the foe, or Disarm the weapon that missed

---

## Vivacious Speed (L3, replaces basic panache speed bonus)

- [ ] Speed bonus while in panache: +10 ft (L3), +15 ft (L7), +20 ft (L11), +25 ft (L15), +30 ft (L19)
- [ ] Without panache: gain half the bonus rounded down to nearest 5-foot increment (passive partial benefit)

---

## Exemplary Finisher (L9)

- [ ] Activates when a Finisher Strike hits
- [ ] Effect is style-specific; correct style effect applied per selected style

---

## Integration Checks

- [ ] Panache state displayed prominently in encounter UI; toggles automatically on Finisher use
- [ ] Correct skill for panache generation linked to selected style
- [ ] Style-specific panache skills: Performance/Demoralize/Feint+Diversion/Grapple+Shove+Trip/Bon Mot
- [ ] Precise Strike bonus type switches correctly between flat and dice depending on whether action is a Finisher
- [ ] No-action-after-finisher rule: subsequent attack actions in the same turn are blocked after a Finisher

## Edge Cases

- [ ] Swashbuckler uses Finisher with no panache: blocked (panache required)
- [ ] Confident Finisher fails: half precise strike damage = flat numeric value, no dice rolled
- [ ] Vivacious Speed with panache at L3 = +10; without panache = +5 (half, rounded to nearest 5)
- [ ] Exemplary Finisher triggers only on a hit, not a miss or failure
- Agent: qa-dungeoncrawler
- Status: pending
