# Acceptance Criteria: Witch Class Mechanics (APG)

## Feature: dc-apg-class-witch
## Source: PF2E Advanced Player's Guide, Chapter 2

---

## Class Fundamentals

- [ ] Class record: HP 6 + Con per level, key ability Intelligence
- [ ] Saves: Trained Fortitude, Trained Reflex, Expert Will
- [ ] No armor proficiency; trained only in unarmored defense
- [ ] Spellcasting tradition determined by patron theme (not fixed at class level)

---

## Patron Theme (Select at L1; Cannot Change)

- [ ] Patron theme determines: spell tradition, patron skill, hex cantrip, familiar's granted spell
- [ ] Themes: Curse (occult), Fate (occult), Fervor (divine), Night (occult), Rune (arcane), Wild (primal), Winter (primal)
- [ ] Patron skill added as Trained skill automatically

---

## Familiar

- [ ] Witch **must** have a familiar; it is a class-locked feature, not optional
- [ ] Familiar gains bonus familiar abilities at L1, L6, L12, L18 (one extra each level milestone)
- [ ] All witch spells are stored in the familiar; communing with familiar is required to prepare spells
- [ ] Familiar death: does NOT erase known spells; replacement familiar provided at next daily prep with all same spells
- [ ] Witch uses prepared (not spontaneous) spellcasting

---

## Spell Learning and Repertoire

- [ ] Familiar starts with 10 cantrips + 5 first-level spells + 1 patron-granted spell
- [ ] Each class level gained: familiar learns 2 new spells chosen by player from tradition's spell list
- [ ] Familiar can learn spells by consuming scrolls (1 hour per spell)
- [ ] Witch can use Learn a Spell to create a consumable written version for familiar to absorb
- [ ] Two witch familiars can teach each other spells via Learn a Spell activity (both familiars present; standard cost applies)
- [ ] Witch cannot prepare spells from another witch's familiar directly

---

## Hexes (Focus Spells)

- [ ] Hexes are focus spells using the focus pool and focus spell rules
- [ ] Only one hex may be cast per turn; attempting a second hex in the same turn fails (actions wasted)
- [ ] Witch starts with focus pool of 1 Focus Point
- [ ] Refocus = 10 minutes communing with familiar; restores 1 Focus Point
- [ ] Hex cantrips (e.g., Evil Eye) do **not** cost Focus Points (free to cast)
- [ ] Hex cantrips still obey the one-hex-per-turn restriction
- [ ] Hex cantrips auto-heighten to half witch level rounded up
- [ ] Hex cantrips are a separate pool from prepared cantrips (do not take up cantrip slots)

---

## Witch Lessons (Tiered Feat Mechanism)

- [ ] Each lesson grants: one hex (added to focus spell options) + one spell added to familiar
- [ ] Lesson tiers: Basic (L2+), Greater (L6+), Major (L10+)
- [ ] **Basic Lessons**: Dreams (veil of dreams + sleep), Elements (elemental betrayal + element choice), Life (life boost + spirit link), Protection (blood ward + mage armor), Vengeance (needle of vengeance + phantom pain)
- [ ] **Greater Lessons**: Mischief (deceiver's cloak + mad monkeys), Shadow (malicious shadow + chilling darkness), Snow (personal blizzard + wall of wind)
- [ ] **Major Lessons**: Death (curse of death + raise dead), Renewal (restorative moment + field of life)

---

## Notable Hexes

- [ ] `Cackle` (1-action): extends another active hex's duration by 1 round (free action in some contexts)
- [ ] `Evil Eye` (hex cantrip): no FP cost; imposes –2 status penalty (sustained); ends early if target succeeds at Will save
- [ ] `Phase Familiar` (reaction hex): familiar becomes incorporeal briefly, avoiding damage

---

## Integration Checks

- [ ] Witch spell preparation uses familiar as the spell repository (visual: "communing with familiar")
- [ ] Patron skill displayed and granted automatically at character creation
- [ ] Familiar spell list grows by 2 per level-up
- [ ] One-hex-per-turn rule enforced across both hex cantrips and regular hexes
- [ ] Focus pool starts at 1; grows with feats that add focus spells
- [ ] Lesson feats are gated by tier level requirements (Basic/Greater/Major)

## Edge Cases

- [ ] Witch familiar dies mid-session: prepared spells for the day remain available; new familiar with same spells restored at next daily prep
- [ ] Two warlocks present: spell transfer via Learn a Spell only — no direct familiar-to-familiar preparation
- [ ] Cackle on a hex cantrip: extends the cantrip's duration (if the cantrip is sustained); does not break one-hex-per-turn rule
- [ ] Evil Eye ends early on Will save success: immediate termination, no partial duration
