# Implementation Notes: APG Core Class Expansions

## Commit
`76e6c627f` — feat(dungeoncrawler): APG core class expansions

## File modified
`sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php`

---

## Changes by class

### Alchemist — Toxicologist
- Updated `CLASS_ADVANCEMENT['alchemist'][1]['auto_features']` entry for `research-field`
- Description now lists four fields (Bomber, Chirurgeon, Mutagenist, **Toxicologist**)
- Toxicologist rules embedded in description: 1-action poison application, class DC substitution, L5 (3 per batch), L15 (double poison, lower DC, no perpetual)

### Barbarian — Superstition
- **No change needed**: `CLASS_ADVANCEMENT['barbarian'][1]` `instinct` description already lists superstition explicitly: "animal, dragon, fury, giant, spirit, or superstition"

### Bard — Warrior Muse
- Added new `CLASS_FEATS['bard']` section (previously absent)
- Added `warrior-muse` feat (`muse: TRUE` marker) — grants Martial Performance at L1, adds fear to repertoire
- Added `martial-performance` feat (requires warrior muse)
- Added `song-of-strength` (L2 composition cantrip, requires warrior muse; +2 circumstance to Athletics)

### Champion — Evil Options
- Added `alignment_options` array to `CLASSES['champion']`
- `good` entry: `access: standard`
- `evil` entry: `access: uncommon` — flags GM unlock requirement in UI; includes note about parallel reaction/devotion-spell structure

### Rogue — New Rackets
- Added `eldritch-trickster-racket` to `CLASS_FEATS['rogue']` (`racket: TRUE` marker)
  - Free multiclass spellcasting archetype dedication at L1
  - Magical Trickster feat available at L2 (not L4)
  - INT as key ability
- Added `mastermind-racket` to `CLASS_FEATS['rogue']` (`racket: TRUE` marker)
  - INT as key ability
  - Trains Society + one knowledge skill
  - Recall Knowledge success → flat-footed until next turn; crit → 1 minute

### Sorcerer — Genie & Nymph Bloodlines
- Added `genie` to `SORCERER_BLOODLINES`: tradition = arcane, `subtype_required: TRUE`, subtypes: `[janni, djinni, efreeti, marid, shaitan]`
- Added `nymph` to `SORCERER_BLOODLINES`: tradition = primal

### Wizard — Staff Nexus Thesis
- Added `staff-nexus` to `CLASS_FEATS['wizard']` (`thesis: TRUE` marker)
- Makeshift staff: 1 cantrip + 1 first-level spell from spellbook
- Charges via expended spell slots only (1 slot = spell-level charges)
- L8: 2 slots/day; L16: 3 slots/day
- Can Craft into any standard staff type; retains original two spells

---

## Data model notes
- `racket: TRUE`, `muse: TRUE`, `thesis: TRUE` markers are metadata flags for UI filtering
- `subtype_required: TRUE` on genie bloodline should trigger incomplete-character validation if subtype is absent at creation
- Evil champion `access: uncommon` is the enforcement hook for GM-unlock UI gate
- Action economy for Toxicologist 1-action poison is documented in description text; EncounterPhaseHandler action-cost logic may need a future follow-up if per-racket/field cost overrides are implemented in the action processor
