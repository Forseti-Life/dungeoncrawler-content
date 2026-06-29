# Feature Brief: Goblin Ancestry Feat — Goblin Weapon Familiarity

- Work item id: dc-cr-goblin-weapon-familiarity
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 7084–7383
- Category: game-mechanic
- Release: 20260412-dungeoncrawler-release-l
- Created: 2026-04-12

## Goal

Implements the Goblin ancestry Feat 1 "Goblin Weapon Familiarity": goblin characters become trained with the dogslicer and horsechopper, gain access to all uncommon goblin weapons, and treat martial goblin weapons as simple weapons plus advanced goblin weapons as martial weapons for proficiency purposes.

## Source reference

> Others might look upon them with disdain, but you know that the weapons of your people are as effective as they are sharp. You are trained with the dogslicer and horsechopper. In addition, you gain access to all uncommon goblin weapons. For the purpose of determining your proficiency, martial goblin weapons are simple weapons and advanced goblin weapons are martial weapons.

## Implementation hint

- Register the feat in the goblin ancestry feat tree at level 1.
- Grant trained proficiency with `dogslicer` and `horsechopper` when the feat is selected.
- Add a proficiency remap in the weapon proficiency resolver: martial goblin weapons count as simple, advanced goblin weapons count as martial, only for characters with this feat.
- Unlock uncommon goblin weapons in item selection/equipment validation for eligible goblin characters.
- This feature is the explicit prerequisite for `dc-cr-goblin-weapon-frenzy`.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: character-scoped write during ancestry feat selection; goblin ancestry prerequisite enforced server-side before feat assignment.
- CSRF expectations: all POST/PATCH requests in character creation and feat-selection flows require `_csrf_request_header_mode: TRUE`.
- Input validation: weapon unlocks must be validated against the goblin weapon tag set; proficiency remap applies only to characters with this feat and never broadens access to non-goblin weapons.
- PII/logging constraints: no PII logged; log character_id, feat_id, granted_weapon_proficiencies only.
