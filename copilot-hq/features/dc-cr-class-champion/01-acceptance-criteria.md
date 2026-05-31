# Acceptance Criteria: dc-cr-class-champion

## Gap analysis reference
- DB sections: core/ch03/Champion (REQs 964–1042+)
- Track B: no existing ChampionService; all implementation is [NEW]
- Depends on: dc-cr-character-class ✓, dc-cr-character-creation ✓

---

## Happy Path

### Identity & Base Statistics
- [ ] `[NEW]` Champion exists as a selectable playable class with STR or DEX as key ability boost at level 1 (player chooses).
- [ ] `[NEW]` Champion HP = 10 + CON modifier per level.
- [ ] `[NEW]` Initial proficiencies: Trained Perception; Expert Fortitude and Will, Trained Reflex; Trained Religion + deity-specific skill + 2 + INT additional; Trained all armor categories (light, medium, heavy); Trained divine spell attacks/DCs (Charisma).

### Deity, Cause & Code
- [ ] `[NEW]` At level 1, player selects a deity and one of three Causes matching alignment: Paladin (Lawful Good), Redeemer (Neutral Good), Liberator (Chaotic Good).
- [ ] `[NEW]` Champion has a mandatory behavioral code; violation removes focus pool and divine ally until atone ritual completed.
- [ ] `[NEW]` Deity-granted weapon (Deific Weapon): uncommon access granted; d4/simple weapon damage die upgraded by one step.
- [ ] `[NEW]` Tenets are hierarchical; higher tenets override lower in conflicts.

### Champion's Reaction (Cause-determined)
- [ ] `[NEW]` Paladin — Retributive Strike: ally gains resistance to all damage = 2 + level; if foe in reach, make a melee Strike.
- [ ] `[NEW]` Redeemer — Glimpse of Redemption: foe chooses (A) ally is unharmed or (B) ally gains resistance = 2 + level; then foe becomes enfeebled 2 until end of its next turn.
- [ ] `[NEW]` Liberator — Liberating Step: ally gains resistance = 2 + level; ally can attempt to break free (new save or free Escape); ally can Step as free action.

### Devotion Spells & Focus Pool
- [ ] `[NEW]` Champion starts with focus pool of 1 Focus Point (max 3 with feats); Refocus = 10 minutes prayer/service.
- [ ] `[NEW]` All good-aligned champions start with lay on hands devotion spell.
- [ ] `[NEW]` Devotion spells auto-heighten to half level rounded up; spell attacks/DCs use Charisma.

### Class Features Unlocks by Level
- [ ] `[NEW]` Level 1: Shield Block general feat (free).
- [ ] `[NEW]` Level 3: Divine Ally selection — Blade Ally (weapon gets property rune + crit specialization), Shield Ally (+2 Hardness, +50% HP/Broken Threshold), or Steed Ally (young animal companion mount).
- [ ] `[NEW]` Level 5: simple and martial weapon proficiency increases to Expert.
- [ ] `[NEW]` Level 7: all armor and unarmored defense increases to Expert; armor specialization for medium and heavy armor unlocked.
- [ ] `[NEW]` Level 7: Weapon Specialization — +2/+3/+4 damage at Expert/Master/Legendary.
- [ ] `[NEW]` Level 9: champion class DC and divine spell attack rolls/DCs increase to Expert.
- [ ] `[NEW]` Level 9: Divine Smite — Champion's Reaction on proc also inflicts persistent good damage = CHA modifier.
- [ ] `[NEW]` Level 9: Fortitude saves increase to Master (successes become critical successes); Reflex saves increase to Expert.
- [ ] `[NEW]` Level 11: Perception increases to Expert; Will saves increase to Master (successes become critical successes).
- [ ] `[NEW]` Level 11: Exalt — Champion's Reaction upgrades to affect nearby allies: Retributive Strike allies within 15 ft can react-Strike at –5; Glimpse resistance applies to all within 15 ft (–2 each); Liberating Step all within 15 ft can Step.
- [ ] `[NEW]` Level 13: all armor and unarmored defense increases to Master; weapon proficiency (simple, martial, unarmed) increases to Master.
- [ ] `[NEW]` Level 15: Greater Weapon Specialization — +4/+6/+8 at Expert/Master/Legendary.
- [ ] `[NEW]` Level 17: champion class DC and divine rolls/DCs increase to Master; all armor + unarmored increases to Legendary.
- [ ] `[NEW]` Level 19: gain hero's defiance devotion spell (defy fate, continue fighting with divine energy).

### Feat Progression
- [ ] `[NEW]` Champion gains a class feat at level 1 and every even-numbered level (2, 4, 6…20).
- [ ] `[NEW]` General feats at levels 3, 7, 11, 15, 19; skill feats at every even level; ancestry feats at levels 5, 9, 13, 17.
- [ ] `[NEW]` Ability boosts at levels 5, 10, 15, 20.

---

## Edge Cases

- [ ] `[NEW]` Code violation: focus pool inaccessible and divine ally benefits suspended until atone ritual; atone restores both.
- [ ] `[NEW]` Oath feat: only one Oath feat per champion enforced.
- [ ] `[NEW]` Exalt Retributive Strike ally at –5 penalty: correctly applied, cannot be reduced.
- [ ] `[NEW]` Cause must match alignment: Paladin requires Lawful Good; invalid cause/alignment combination blocked.

---

## Failure Modes

- [ ] `[TEST-ONLY]` Level-gated features do not appear before required level.
- [ ] `[TEST-ONLY]` Focus pool at 0: devotion spells blocked.
- [ ] `[TEST-ONLY]` Invalid deity/cause combination rejected.
- [ ] `[TEST-ONLY]` Divine ally selection is permanent; attempting to change post-selection is blocked.

---

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms
