# Feature Brief: APG Archetypes System

- Work item id: dc-apg-archetypes
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: 
20260408-dungeoncrawler-release-h
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Advanced Player's Guide, Chapter 3
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: apg/ch03/Archetype System Rules (General), apg/ch03/Acrobat, apg/ch03/Archaeologist, apg/ch03/Archer, apg/ch03/Assassin, apg/ch03/Bastion, apg/ch03/Beastmaster, apg/ch03/Blessed One, apg/ch03/Bounty Hunter, apg/ch03/Cavalier, apg/ch03/Celebrity, apg/ch03/Dandy, apg/ch03/Dragon Disciple, apg/ch03/Dual-Weapon Warrior, apg/ch03/Duelist, apg/ch03/Eldritch Archer, apg/ch03/Familiar Master, apg/ch03/Gladiator, apg/ch03/Herbalist, apg/ch03/Horizon Walker, apg/ch03/Linguist, apg/ch03/Loremaster, apg/ch03/Marshal, apg/ch03/Martial Artist, apg/ch03/Mauler, apg/ch03/Medic, apg/ch03/Pirate, apg/ch03/Poisoner, apg/ch03/Ritualist, apg/ch03/Scout, apg/ch03/Scroll Trickster, apg/ch03/Scrounger, apg/ch03/Sentinel, apg/ch03/Shadowdancer, apg/ch03/Snarecrafter, apg/ch03/Talisman Dabbler, apg/ch03/Vigilante, apg/ch03/Viking, apg/ch03/Weapon Improviser
- Depends on: dc-cr-multiclass-archetype, dc-cr-character-class, dc-cr-character-leveling

## Goal

Implement the APG archetype system — Dedication feats, follow-on archetype feats, and the three archetype types (Class/Multiclass/Basic) — enabling alternate class feat pathways and multiclass dipping within the existing feat slot system.

## Source reference

> "Archetypes allow you to spend class feats to gain abilities outside your class; a Dedication feat grants basic access, and subsequent feats expand those abilities."

## Implementation hint

Archetypes are modeled as a `ArchetypeEntity` with fields: name, archetype_type enum (class/multiclass/basic), dedication_feat_id, follow_on_feats[], prerequisites[]. `DedicationFeatValidator` checks the archetype prerequisites before allowing selection; a character may only have one multiclass archetype Dedication unless they have the relevant feat. `ArchetypeFeatSlotService` marks class feat slots consumed by archetype feats; the character may not take more than half their class feats from archetypes (for multiclass) without the relevant unlock. APG adds ~30 archetypes; implement as a bulk data import extending the feat catalog.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; Dedication feat prerequisites enforced server-side; archetype feat ratio cap enforced server-side.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Archetype ID must reference a valid archetype entity; Dedication feat prerequisites validated against current character state; feat type (class/archetype) validated before slot assignment.
- PII/logging constraints: no PII logged; log character_id, archetype_id, dedication_feat_id, follow_on_feats_selected[]; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
