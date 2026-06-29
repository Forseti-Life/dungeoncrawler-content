# Test Plan Design: dc-apg-class-swashbuckler

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:54:16+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-apg-class-swashbuckler/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-apg-class-swashbuckler "<brief summary>"
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
