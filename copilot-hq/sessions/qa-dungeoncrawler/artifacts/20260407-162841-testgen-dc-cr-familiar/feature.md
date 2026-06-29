# Feature Brief: Familiar System

- Work item id: dc-cr-familiar
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: planned
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: game-mechanic
- Created: 2026-02-26

## Goal

Allow spellcasting classes (wizard, sorcerer, witch, etc.) to have a magical familiar that grants passive bonuses and performs minor helper actions. Familiars have a distinct rules subsystem from animal companions: they have familiar abilities chosen each day, no combat stats, and serve as an extension of the caster's magic. This adds flavor and utility options for caster builds.

## Source reference

> "This chapter also details animal companions, familiars, and multiclass archetypes that expand your character's abilities." (Chapter 3: Classes)

## Implementation hint

Content type: `familiar` with base stat fields and a list of selectable familiar abilities (each ability is a separate node/record). Link to owning character. UI for daily ability selection. Distinct from `animal_companion`: familiars don't have attack actions but do support actions like Aid, Deliver Touch Spells, etc.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Security AC exemption: Familiar data is character-scoped; daily ability selection is server-validated to enforce class limits.

## Roadmap section

- Roadmap: Core Rulebook
