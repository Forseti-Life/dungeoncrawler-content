# Feature Brief: Bard Class Mechanics

- Work item id: dc-cr-class-bard
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch03
- Category: class
- Created: 2026-04-07
- DB sections: core/ch03/Bard
- Depends on: dc-cr-character-class, dc-cr-spellcasting, dc-cr-focus-spells

## Goal

Implement Bard class mechanics — Muse, Composition Spells (Inspire Courage/Heroics/Defense), Occult spontaneous casting, and Signature Spells — so players can provide aura-style performance buffs while maintaining a full spellcasting progression.

## Source reference

> "Composition spells are a special type of spell requiring Performance to cast; Inspire Courage is a cantrip that grants a +1 status bonus to attack rolls, damage rolls, and saves against fear to all allies within 60 feet."

## Implementation hint

Composition spells use the Bard's Performance modifier as the spellcasting modifier and are always heightened to half caster level; implement as a `CompositionSpellCaster` subclass of `SpontaneousCaster`. `Muse` is a required selection at character creation that unlocks signature spells and a specific composition cantrip. `SignatureSpell` entities are stored on the character and allow free heightening of those specific spell IDs. `OccultBreadth` grants an extra spell slot at level−1 rank; compute in the spell slot allocation routine.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; composition spell activation gated to encounter/exploration phase for the owning character.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Muse enum restricted to [Enigma, Maestro, Polymath]; signature spell IDs must be from the character's known repertoire; composition spells validate Performance skill proficiency.
- PII/logging constraints: no PII logged; log character_id, composition_spell_cast, heightened_rank; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
