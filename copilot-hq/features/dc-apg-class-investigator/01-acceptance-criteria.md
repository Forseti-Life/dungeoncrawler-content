# Acceptance Criteria: Investigator Class Mechanics (APG)

## Feature: dc-apg-class-investigator
## Source: PF2E Advanced Player's Guide, Chapter 2

---

## Class Fundamentals

- [ ] Class record: HP 8 + Con per level, key ability Intelligence
- [ ] Starting proficiencies: Expert Perception, Expert Reflex, Expert Will, Trained Fortitude
- [ ] Trained in light armor and unarmored defense; trained in simple weapons and the rapier
- [ ] Society is a class skill (always Trained)
- [ ] Total trained skills = 4 + Int modifier + 1 (Society) + 1 (methodology skill)

---

## Devise a Stratagem

- [ ] 1-action activity with the Fortune trait
- [ ] Player rolls a d20 immediately; the result is stored and applied to the next qualifying Strike this turn
- [ ] Qualifying weapons: agile melee, finesse melee, ranged, sap, agile unarmed, finesse unarmed
- [ ] On qualifying Strike: substitute Intelligence modifier for Strength/Dexterity on the attack roll
- [ ] Frequency: 1/round (cannot use twice in the same turn)
- [ ] If the target is an **active lead**, action cost is a Free Action instead of 1 Action

---

## Pursue a Lead

- [ ] 1-minute exploration activity
- [ ] Grants +1 circumstance bonus to investigating checks against the designated lead target
- [ ] Maximum 2 active leads may be maintained simultaneously
- [ ] Designating a 3rd lead automatically ends the oldest existing lead
- [ ] Lead target must be a specific creature, object, or location

---

## Clue In

- [ ] Reaction, frequency 1/10 minutes
- [ ] Triggers on a successful investigative check
- [ ] Shares the circumstance bonus from Pursue a Lead with one ally within 30 feet

---

## Strategic Strike (Precision Damage)

- [ ] Deals precision damage on attacks preceded by Devise a Stratagem
- [ ] Damage progression: 1d6 (L1), 2d6 (L5), 3d6 (L9), 4d6 (L13), 5d6 (L17)
- [ ] Damage type is precision (not a specific elemental or physical type)

---

## Methodologies (Select One at L1)

### Alchemical Sciences
- [ ] Grants Crafting proficiency and Alchemical Crafting feat automatically
- [ ] Formula book tracks known alchemical formulas
- [ ] Daily preparations produce versatile vials equal to Intelligence modifier
- [ ] `Quick Tincture` 1-action activity: consume one versatile vial to produce an alchemical item from formulas known

### Empiricism
- [ ] Grants proficiency in one Intelligence-based skill
- [ ] Grants `That's Odd` feat
- [ ] `Expeditious Inspection` free action (1/10 min): instant Recall Knowledge, Seek, or Sense Motive
- [ ] Empiricism removes the lead requirement for Devise a Stratagem — can target any creature

### Forensic Medicine
- [ ] Grants Medicine proficiency, Battle Medicine feat, and Forensic Acumen feat
- [ ] Battle Medicine healing bonus = investigator level (added to base Battle Medicine result)
- [ ] Forensic Medicine reduces Battle Medicine recovery immunity from 1 day to 1 hour

### Interrogation
- [ ] Grants Diplomacy proficiency and `No Cause for Alarm` feat
- [ ] Pursue a Lead can be used in social encounters and conversation mode
- [ ] `Pointed Question` 1-action Intimidation/Deception-based activity exposes inconsistency in a target's statements

---

## Integration Checks

- [ ] Devise a Stratagem stored roll is cleared at end of turn whether used or not
- [ ] Free-action cost reduction applies only when target is an active lead
- [ ] 2-lead cap enforced: adding a 3rd lead removes the oldest from the lead tracker
- [ ] Strategic Strike precision damage does not stack with sneak attack or other precision damage sources (only highest applies per standard rules)
- [ ] Alchemical Sciences vial count updates when Intelligence modifier changes
- [ ] Forensic Medicine Battle Medicine immunity timer uses 1-hour duration correctly

## Edge Cases

- [ ] If Devise a Stratagem result is stored but no qualifying Strike is made this turn, stored result is discarded
- [ ] Empiricism's lead-requirement waiver applies only to Devise a Stratagem action cost (not to other lead-dependent effects)
- [ ] Pointed Question requires the target to have made a statement this encounter (GM check; flagged in UI)
