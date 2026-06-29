# Acceptance Criteria: dc-cr-gnome-ancestry

## Gap analysis reference
- DB sections: core/ch02 (Gnome Ancestry)
- Depends on: dc-cr-ancestry-system ✓, dc-cr-heritage-system ✓, dc-cr-languages ✓

---

## Happy Path

### Core Stats
- [ ] `[NEW]` Gnome ancestry: HP 8, Small size, Speed 25 feet.
- [ ] `[NEW]` Ability boosts: Constitution, Charisma, and one free boost.
- [ ] `[NEW]` Ability flaw: Strength.
- [ ] `[NEW]` Traits: Gnome, Humanoid.
- [ ] `[NEW]` Starting languages: Common, Gnomish, Sylvan.
- [ ] `[NEW]` Bonus languages: Draconic, Dwarven, Elven, Goblin, Jotun, Orcish, or one uncommon language — one per point of positive Intelligence modifier.

### Senses
- [ ] `[NEW]` Gnomes have Low-Light Vision (see in dim light as bright light; see twice as far from light sources in darkness).

### Heritage Selection (mandatory at character creation)
- [ ] `[NEW]` Selecting Gnome ancestry unlocks exactly five heritages: Chameleon, Fey-touched, Sensate, Umbral, and Wellspring.
- [ ] `[NEW]` Exactly one heritage must be chosen before character creation can be completed.

### Ancestry Feats (1st-level Gnome feats available at character creation)
- [ ] `[NEW]` Animal Accomplice (1st): a familiar from a limited list (bat, cat, gecko, etc.) using the familiar rules.
- [ ] `[NEW]` Burrow Elocutionist (1st): speak with and understand burrowing animals as if using speak with animals.
- [ ] `[NEW]` Fey Fellowship (1st): see dc-cr-fey-fellowship acceptance criteria.
- [ ] `[NEW]` First World Magic (1st): see dc-cr-first-world-magic acceptance criteria.
- [ ] `[NEW]` Gnome Obsession (1st): trained in one Lore subcategory; +1 circumstance bonus to rolls for that obsession during downtime.
- [ ] `[NEW]` Gnome Weapon Familiarity (1st): trained in glaive and kukri; gnome-trait weapons treated as martial if already in martial group.
- [ ] `[NEW]` Illusion Sense (1st): +1 circumstance bonus to Will saves vs. illusions; passive Perception to disbelieve illusions rolled automatically when entering their area.
- [ ] `[NEW]` Natural Performer (1st): trained in Performance; gain one Performance-related power (singing, dancing, or acting — chosen at selection).
- [ ] `[NEW]` Vibrant Display (1st): use 2 actions to display vivid coloration; creatures within 10 ft must succeed a Will save or become fascinated until end of next turn.

---

## Edge Cases
- [ ] `[NEW]` Gnome size is Small — inventory Bulk limits and some combat effects apply Small-size rules.
- [ ] `[NEW]` Con + Cha boosts applied before free boost; free boost may NOT be applied to Con or Cha a second time.
- [ ] `[NEW]` Bonus languages slot count = max(0, int_modifier); list is restricted to the six listed options plus one DM-approved uncommon language.

## Failure Modes
- [ ] `[TEST-ONLY]` HP 8 (not 10): must not inherit Dwarf's HP value.
- [ ] `[TEST-ONLY]` Speed 25 ft (not 20): must not inherit Dwarf's Speed penalty.
- [ ] `[TEST-ONLY]` Str flaw applied automatically: must not be overrideable by player at character creation.

## Security acceptance criteria
- Security AC exemption: game-mechanic ancestry selection; no new routes beyond existing character creation flow
