# Outbox: Wizard Class Mechanics Implementation

- Status: done
- Summary: Wizard had significant gaps across five areas in `CharacterManager.php`. `CLASSES['wizard']` was missing `armor_proficiency`, all three core subsystems (`arcane_school`, `arcane_thesis`, `arcane_bond`), `drain_bonded_item`, and `spellbook` mechanics. `CLASS_FEATS['wizard']` had only 7 L1 entries and nothing at L2–L20. `FOCUS_POOLS['wizard']` was entirely absent. `CLASS_ADVANCEMENT['wizard']` had two bugs: Expert Spellcaster was wrongly placed at L3 (should be L7 per PF2e CRB), and L1 was missing Arcane Bond, Arcane Thesis, and Drain Bonded Item entries. A new `ARCANE_SCHOOLS` constant was added covering all 8 specialist schools plus Universalist with their focus spells. All gaps filled; PHP lint clean; 55 dc_requirements rows; site HTTP 200.

## Commits
- `4f612f4a3` (forseti.life) — `feat(dungeoncrawler): complete Wizard class mechanics` (268 insertions, 12 deletions)

## What changed

### CLASSES['wizard'] — additions
- `armor_proficiency`: unarmored only
- `arcane_school`: description + 8 schools + universalist + extra_slot rule
- `arcane_thesis`: 5 options (Spell Blending, Spell Substitution, Improved Familiar Attunement, Experimental Spellshaping, Staff Nexus) with full benefit text
- `arcane_bond`: bonded-item and familiar options with Drain Bonded Item hookup
- `drain_bonded_item`: free action, once per day, recover any expended spell slot
- `spellbook`: starting_spells 10, starting_cantrips 5, prepared_type, Learn a Spell cost, tradition

### ARCANE_SCHOOLS constant — new
- 9 entries: abjuration, conjuration, divination, enchantment, evocation, illusion, necromancy, transmutation, universalist
- Each entry: id, name, description, school_spells, primary_spell, focus_spells, benefit text

### CLASS_FEATS['wizard'] — added L2–L20
- L2: Cantrip Expansion, Conceal Spell, Enhanced Familiar, Nonlethal Spell
- L4: Bespell Weapon, Linked Focus, Spell Penetration (feat), Steady Spellcasting
- L6: Advanced School Spell, Bond Conservation, Universal Versatility
- L8: Greater Mental Evolution, Overwhelming Energy, Quickened Casting
- L10: Scroll Savant, Clever Counterspell
- L12: Magic Sense, Reflect Spell
- L14: Effortless Concentration, Alter Reality
- L16: Spell Combination, Infinite Eye
- L18: Reprepare Spell, Infinite Possibilities
- L20: Spell Mastery, Metamagic Mastery

### FOCUS_POOLS['wizard'] — new entry
- start 1, cap 3, expand_per_source TRUE, Refocus: study spellbook 10 minutes

### CLASS_ADVANCEMENT['wizard'] — fixed and expanded
- L1: added arcane-bond, arcane-thesis, drain-bonded-item (were missing)
- L3: removed Expert Spellcaster (was wrong — PF2e CRB L7)
- L5: added Lightning Reflexes (Reflex → Expert)
- L7: Expert Spellcaster added here (correct) + Wizard Weapon Expertise (was already here)
- L17 Resolve: added "critical fail → fail" note (was missing)
- L20: removed Supreme Spellcaster (duplicate of Legendary already at L15)

## Verification
- `php -l CharacterManager.php` → No syntax errors ✓
- `dc_requirements` wizard rows: 55 (> 0) ✓
- Site `https://dungeoncrawler.forseti.life` → HTTP 200 ✓
- ARCANE_SCHOOLS: 9 entries (8 specialist + universalist) ✓
- CLASS_FEATS['wizard']: L1–L20 ✓
- FOCUS_POOLS['wizard']: present ✓
- CLASS_ADVANCEMENT L7 now has Expert Spellcaster + Weapon Expertise ✓

## Rollback
- `git revert 4f612f4a3` (forseti.life) restores prior state

## Next actions
- Await QA verification of dc-cr-class-wizard

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 22
- Rationale: Wizard is the most popular arcane class and the reference point for all arcane spellcasting; Arcane School and Arcane Thesis are its core identity systems. Missing these would produce broken character creation for the most common player choice.
