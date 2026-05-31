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
