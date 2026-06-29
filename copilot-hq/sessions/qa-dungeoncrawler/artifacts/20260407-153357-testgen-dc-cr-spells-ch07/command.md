# Test Plan Design: dc-cr-spells-ch07

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:33:57+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-spells-ch07/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-spells-ch07 "<brief summary>"
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

# Acceptance Criteria: dc-cr-spells-ch07

## Gap analysis reference
- DB sections: core/ch07 — Casting Spells (39), Spell Slots and Spellcasting Types (14), Arcane/Divine/Occult/Primal Spell Lists (11 each), Focus Spells by Class (9), Special Spell Types (13), Spell Stat Block Format (10), Traditions and Schools (6)
- Depends on: dc-cr-spellcasting, dc-cr-focus-spells, dc-cr-rituals

---

## Happy Path

### Traditions and Schools
- [ ] `[NEW]` Four distinct magical traditions: Arcane, Divine, Occult, Primal.
- [ ] `[NEW]` Each spell belongs to one or more traditions (via spell list membership) and exactly one school (8 schools: Abjuration, Conjuration, Divination, Enchantment, Evocation, Illusion, Necromancy, Transmutation).
- [ ] `[NEW]` A caster's tradition is determined by class (and sometimes bloodline/deity); tradition trait added to all spells cast.
- [ ] `[NEW]` Schools define the spell's nature; casters may specialize in a specific school (see class features).
- [ ] `[NEW]` Essence classification (mental/vital/material/spiritual) captured in system lore and used for resistances/immunities.

### Spell Slots and Spellcasting Types
- [ ] `[NEW]` Each spellcaster character tracks spell slots independently by spell level (1–10); not a shared pool.
- [ ] `[NEW]` Spell slots refresh on daily preparation.
- [ ] `[NEW]` Spells have a spell level (1–10) independent of character level.

#### Prepared Spellcasters (Cleric, Druid, Wizard)
- [ ] `[NEW]` Prepared spellcasters select spells during daily prep; each spell occupies a slot.
- [ ] `[NEW]` Casting expends the slot; the same spell must be prepared multiple times to cast multiple times.
- [ ] `[NEW]` Cantrips: prepared once during daily prep; castable indefinitely without expending slots.

#### Spontaneous Spellcasters (Bard, Sorcerer)
- [ ] `[NEW]` Spontaneous casters have a fixed spell repertoire; any known spell castable using any available slot of appropriate level.
- [ ] `[NEW]` Daily prep refreshes slots, not the repertoire.
- [ ] `[NEW]` Signature spells (class feature): can be heightened spontaneously even if only known at one level.
- [ ] `[NEW]` Spontaneous casters can only heighten to levels where they have the spell in their repertoire (except signature spells).

### Heightening
- [ ] `[NEW]` Heightening: casting a spell in a higher slot raises its effective level.
- [ ] `[NEW]` Heightened entries at specific levels ("Heightened (4th)") apply only at that level.
- [ ] `[NEW]` Cumulative entries ("Heightened (+2)") stack above base level at each step.

### Cantrips and Focus Spells
- [ ] `[NEW]` Cantrips: effective level = ceil(character level / 2); no slot cost; auto-heightened to caster's maximum effective spell level.
- [ ] `[NEW]` Focus spells: effective level = ceil(character level / 2).
- [ ] `[NEW]` Focus Pool: separate from spell slots; max capacity = 3 (hard cap); each focus ability adds 1 (capped at 3).
- [ ] `[NEW]` Refocus: 10-minute activity restores 1 Focus Point; requires class-specific deeds.
- [ ] `[NEW]` Daily preparation restores all Focus Points.
- [ ] `[NEW]` Focus spells cannot be placed in spell slots; spell slots cannot activate focus spells.
- [ ] `[NEW]` Non-spellcasters with focus spells gain proficiency from granting ability but are not "spellcasters".
- [ ] `[NEW]` Multiple focus sources share one pool; points fungible across all focus spells.

### Innate Spells
- [ ] `[NEW]` Innate spells granted by ancestry or items; refresh daily; separate from class spell slots.
- [ ] `[NEW]` Innate spells use Charisma modifier for attack/DC by default; caster always at least Trained in innate spell rolls/DCs.
- [ ] `[NEW]` Innate cantrips: auto-heightened; innate non-cantrips: once/day; cannot be heightened.

### Casting Spells
- [ ] `[NEW]` Casting a spell is an activity costing the listed number of actions (1/2/3/reaction/free).
- [ ] `[NEW]` Effect applies at end of all casting actions; spell can be disrupted mid-cast.
- [ ] `[NEW]` Spells with casting times > 1 round have Exploration trait; cannot be used in encounters.

#### Components
- [ ] `[NEW]` Four component types: Material, Somatic, Verbal, Focus; each adds corresponding trait.
- [ ] `[NEW]` Material: free hand required; consumed on cast (even if disrupted); covered by material component pouch.
- [ ] `[NEW]` Somatic: must be able to gesture; can hold item in hand; must be unrestrained.
- [ ] `[NEW]` Verbal: must be able to speak; generates noise; reveals spellcasting.
- [ ] `[NEW]` Focus component: specific item required; can be returned to holster after cast.
- [ ] `[NEW]` Disrupted spell: slot/Focus Point/action expended; no effect; if disrupted during Sustain → spell ends immediately.
- [ ] `[NEW]` Class-specific component substitutions implemented per class rules.
- [ ] `[NEW]` Innate spells always allow Material→Somatic substitution.

#### Metamagic
- [ ] `[NEW]` Metamagic declared before casting; applies effects to the subsequent Cast a Spell activity.
- [ ] `[NEW]` Metamagic wasted if anything other than Cast a Spell immediately follows.

### Spell Attacks and DCs
- [ ] `[NEW]` Spell attack roll: spellcasting mod + proficiency + bonuses + penalties.
- [ ] `[NEW]` Spell DC: 10 + spellcasting mod + proficiency + bonuses + penalties.
- [ ] `[NEW]` Multiple attack penalty applies to spell attacks.
- [ ] `[NEW]` Weapon-specific bonuses (weapon specialization etc.) do NOT apply to spell attacks.
- [ ] `[NEW]` Spell attacks target AC; basic saving throw spells use spell DC.
- [ ] `[NEW]` Basic saving throw degrees: Crit Success = 0 damage; Success = half; Failure = full; Crit Failure = double.

### Area, Range, Targeting
- [ ] `[NEW]` Area spell measurement follows burst/cone/emanation/line geometry (see ch09).
- [ ] `[NEW]` Touch spells use unarmed reach; range for touch starts at 0 ft.
- [ ] `[NEW]` Willing targets declared by player at any time (regardless of condition).
- [ ] `[NEW]` Invalid target: spell fails on that target only (does not invalidate other targets).
- [ ] `[NEW]` Line of effect required from caster to spell target/origin point.

### Durations
- [ ] `[NEW]` Duration types: instantaneous, round-based, timed, sustained, until next daily prep, unlimited.
- [ ] `[NEW]` Round-based durations: decrement at start of caster's turn; ends at 0.
- [ ] `[NEW]` Sustained spells: last until end of next turn; require 1 action (Concentrate) to sustain; sustaining > 100 rounds causes fatigue + ends spell (unless "sustained up to N" specified).
- [ ] `[NEW]` If caster dies/incapacitated: timed spells continue until expiry; sustained spells end if not sustained.
- [ ] `[NEW]` Dismiss action (1 action, Concentrate): ends a dismissible spell.

### Special Spell Types
- [ ] `[NEW]` Illusions with disbelief: Crit Success = reveals nothing real; Success = hazy but may not fully remove.
- [ ] `[NEW]` Physical proof of illusion reveals it but does not automatically grant disbelief.
- [ ] `[NEW]` Counteract mechanism: separate check from normal spell attack; light/darkness can always counteract each other.
- [ ] `[NEW]` Non-magical light cannot overcome magical darkness.
- [ ] `[NEW]` Incapacitation trait: hard counter for creatures above half caster's level.
- [ ] `[NEW]` Polymorph (battle form): gear absorbed; no casting; no speech; no manipulate actions.
- [ ] `[NEW]` Summoned creatures: use 2 actions on appearance turn; minion rules apply.
- [ ] `[NEW]` Trigger-set spells: react when sensor observes matching trigger; can be fooled by disguise/illusion.
- [ ] `[NEW]` Spell identification: auto if prepared/known; else Recall Knowledge; pre-existing effects require Identify Magic.

### Spell Stat Block Format
- [ ] `[NEW]` Spell data model captures: name, type, level, school, traditions, rarity, cast actions, components, trigger, cost, range, area, targets, save type, duration, description, heightened effects.
- [ ] `[NEW]` Heightened entries displayed: specific levels ("Heightened (4th)") or cumulative ("Heightened (+2)").
- [ ] `[NEW]` "H" flag = has heightened benefits; "U" = uncommon; "R" = rare.
- [ ] `[NEW]` Rare/uncommon spells require access restriction.
- [ ] `[NEW]` Spell variants (e.g., Harm vs Heal) implemented as distinct spells.
- [ ] `[NEW]` All spells in all four tradition spell lists implemented with full stat blocks (levels 0–10).

### Spell Lists (Content)
- [ ] `[NEW]` All Arcane spell list entries (cantrips through 10th) implemented and accessible to arcane casters.
- [ ] `[NEW]` All Divine spell list entries implemented and accessible to divine casters.
- [ ] `[NEW]` All Occult spell list entries implemented and accessible to occult casters.
- [ ] `[NEW]` All Primal spell list entries implemented and accessible to primal casters.

### Focus Spells by Class
- [ ] `[NEW]` Bard composition focus spells implemented (Inspire Courage, Inspire Defense, Inspire Competence, Counter Performance, Lingering Composition, etc.).
- [ ] `[NEW]` Champion devotion focus spells implemented (Lay on Hands, Litany Against Wrath, Litany of Righteousness, Hero's Defiance, etc.).
- [ ] `[NEW]` Cleric domain focus spells organized by domain; each selection adds 1 Focus Point.
- [ ] `[NEW]` Druid order focus spells implemented per order (Animal/Leaf/Stone/Storm/Wild); Wild Shape foundation at cantrip level with heightened versions (2nd/4th/6th).
- [ ] `[NEW]` Monk ki focus spells implemented (Ki Strike, Ki Rush, Wholeness of Body, Ki Blast, Quivering Palm, Stunning Fist, etc.).
- [ ] `[NEW]` Sorcerer bloodline focus spells implemented per bloodline (Draconic, Demonic, Undead, Fey, Angelic, Imperial, etc.).
- [ ] `[NEW]` Wizard school focus spells implemented per school (Augment Summoning, Charming Words, Hand of the Apprentice, Diviner's Sight, etc.).
- [ ] `[NEW]` Domain advanced focus spells (levels 4–6) available once class feature grants them.

---

## Edge Cases
- [ ] `[NEW]` Spontaneous caster attempting to heighten non-signature spell to a level they don't have it: blocked.
- [ ] `[NEW]` Metamagic followed by non-Cast action: metamagic wasted (no effect, no refund).
- [ ] `[NEW]` Sustain > 100 rounds: fatigue applied and spell ends.
- [ ] `[NEW]` Focus Pool at 3 with additional focus source: pool stays at 3 (hard cap).

## Failure Modes
- [ ] `[TEST-ONLY]` Disrupted spell still expends slot/Focus Point/action.
- [ ] `[TEST-ONLY]` Invalid target: spell fires on valid targets; invalid target excluded (not a full-fizzle).
- [ ] `[TEST-ONLY]` Weapon specialization bonus does NOT apply to spell attack rolls.
- [ ] `[TEST-ONLY]` Innate non-cantrip used twice in one day: blocked on second use.

## Security acceptance criteria
- Security AC exemption: game-mechanic spellcasting system; no new routes or user-facing input beyond existing character creation and encounter phase forms
- Agent: qa-dungeoncrawler
- Status: pending
