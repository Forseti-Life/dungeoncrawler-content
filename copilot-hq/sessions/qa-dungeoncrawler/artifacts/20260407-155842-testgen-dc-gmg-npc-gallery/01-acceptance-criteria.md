# Acceptance Criteria: GMG NPC Gallery System

## Feature: dc-gmg-npc-gallery
## Source: PF2E Gamemastery Guide, Chapter 2 (sub-feature of dc-gmg-hazards)

---

## NPC Gallery Prebuilt Stat Blocks

- [ ] NPC Gallery entries are prebuilt creature stat blocks tagged with an NPC archetype classifier
- [ ] Gallery includes common archetypes: Guard, Soldier, Bandit, Merchant, Assassin, Informant, Noble, Cultist, Innkeeper, Sailor
- [ ] Each gallery entry has a level range (e.g., 1, 3, 5, 7...) for encounter-building selection
- [ ] NPC stat blocks follow the standard creature stat block format (same schema as Bestiary creatures)

## Elite and Weak Adjustments

- [ ] Elite template: +2 to all modifiers, attack bonus, DC, saves; +HP based on level tier
- [ ] Weak template: –2 to all modifiers, attack bonus, DC, saves; –HP based on level tier
- [ ] Elite/Weak applies as a modifier overlay — base stat block unchanged
- [ ] Elite/Weak adjustments stack with the standard creature level adjustment rules

## GM Usage

- [ ] GM can select a Gallery NPC from creature selector during scene setup
- [ ] Gallery NPCs can be used as allies, enemies, or neutral parties
- [ ] Selected NPCs can be renamed and given custom descriptions without altering mechanical stats
- [ ] HP tracking, condition tracking, and action management function identically to standard creatures

## Integration Checks

- [ ] NPC Gallery entries appear in creature selector alongside Bestiary creatures (filterable by "NPC" tag)
- [ ] Elite/Weak overlay correctly recalculates all derived statistics at point of application
- [ ] Depends on dc-cr-npc-system being implemented; if not yet active, Gallery entries use creature framework as fallback

## Edge Cases

- [ ] Elite + Weak applied simultaneously: disallowed (mutually exclusive templates)
- [ ] Custom-renamed NPC: rename persists in session log and encounter history; does not affect stat block identity
