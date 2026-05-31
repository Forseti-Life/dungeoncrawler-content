# Acceptance Criteria: APG Core Class Expansions

## Feature: dc-apg-class-expansions
## Source: PF2E Advanced Player's Guide, Chapter 2 (Core Class Expansions)

---

## Alchemist — Toxicologist Research Field

- [ ] Toxicologist added as a new research field option for Alchemist
- [ ] Starts with 2 common 1st-level alchemical poison formulas in formula book
- [ ] Applying injury poison costs 1 action (standard is 2 actions)
- [ ] May substitute class DC for poison save DC when using infused poisons
- [ ] L5 Field Discovery: create 3 poisons per batch (instead of 2)
- [ ] L15 Greater Field Discovery: apply two injury poisons to same weapon simultaneously; combined as double poison with lower DC; cannot use perpetual poisons with this option

---

## Barbarian — Superstition Instinct

- [ ] Superstition added as new instinct option for Barbarian
- [ ] Anathema: willingly accepting magical spell effects (from any source)
- [ ] Restriction includes spell-casting items (wands, scrolls that cast spells)
- [ ] Does **not** restrict potions or non-spell magic item activations
- [ ] Continuing to associate with allies who repeatedly cast magic on the barbarian against their will is also anathema

---

## Bard — Warrior Muse

- [ ] Warrior added as a new muse option for Bard
- [ ] Warrior muse grants Martial Performance feat at L1
- [ ] Adds fear to repertoire at L1
- [ ] `Song of Strength` composition cantrip unlocked via a L2 feat for warrior muse bards; grants +2 circumstance bonus to Athletics checks for duration

---

## Champion — Evil Alignment Options

- [ ] Champion system supports evil-aligned options as Uncommon access
- [ ] Evil champion tenets require GM access grant (flagged as Uncommon in UI)
- [ ] Evil champions gain appropriate alignment-based champion's reaction and devotion spells (parallel to good champion structure)

---

## Rogue — New Rackets

- [ ] Eldritch Trickster: free multiclass spellcasting archetype dedication at L1
- [ ] Eldritch Trickster: Magical Trickster feat available at L2 (down from L4)
- [ ] Mastermind: Intelligence as key ability; gains Society + one knowledge skill
- [ ] Mastermind: successful Recall Knowledge renders target flat-footed until next turn; critical success = 1 minute
- [ ] Both rackets allow Intelligence as the key ability score choice

---

## Sorcerer — New Bloodlines

- [ ] Genie bloodline: arcane spell list
- [ ] Genie bloodline subtype selection at L1: Janni, Djinni, Efreeti, Marid, or Shaitan; determines certain granted spells
- [ ] Nymph bloodline: primal spell list
- [ ] Both bloodlines follow standard bloodline structure (granted spells, bloodline spells, blood magic effect)

---

## Wizard — Staff Nexus Arcane Thesis

- [ ] Staff Nexus added as new arcane thesis option for Wizard
- [ ] Wizard starts with a makeshift staff: 1 cantrip + 1 first-level spell from spellbook
- [ ] Makeshift staff gains charges only via expended spell slots (not standard staff charging rules)
- [ ] L8: may expend 2 spell slots; combined spell levels = charges added
- [ ] L16: may expend up to 3 spell slots; combined spell levels = charges added
- [ ] Can Craft makeshift staff into any standard staff type (standard cost); retains the two original spells

---

## Integration Checks

- [ ] Each expansion option appears within the correct class's selection screen (research field, instinct, muse, racket, bloodline, thesis)
- [ ] Toxicologist 1-action poison application replaces standard 2-action in action economy
- [ ] Superstition anathema triggers a warning/tracking flag when relevant (GM tool)
- [ ] Warrior muse Martial Performance feat granted automatically at bard creation
- [ ] Evil champion options gated behind GM unlock / Uncommon access flag
- [ ] Mastermind flat-footed state from Recall Knowledge correctly linked to timing (until next turn / 1 minute)
- [ ] Staff Nexus makeshift staff charge rules use expended-slot logic, not passive daily recharge

## Edge Cases

- [ ] Toxicologist double-poison at L15: both poisons' effects apply; save DC = lower of the two DCs
- [ ] Mastermind Recall Knowledge flat-footed: applies to ALL attacks against the target this turn (not just investigator)
- [ ] Genie subtype: if subtype not selected at creation, flag as incomplete character (required choice)
- [ ] Staff Nexus cantrip in makeshift staff: does not cost a charge to cast (cantrips are free)
