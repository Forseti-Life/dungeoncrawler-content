# Feature Brief: Multiclass Archetype System

- Work item id: dc-cr-multiclass-archetype
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

Allow characters to branch into a second class via the archetype feat system, gaining access to class feats from another class at the cost of their own class feats. Multiclass archetypes provide a safe, bounded cross-class customization path without the complexity of PF1e multiclassing. This enables highly personalized character builds and dramatically increases replayability.

## Source reference

> "This chapter also details animal companions, familiars, and multiclass archetypes that expand your character's abilities." (Chapter 3: Classes)

## Implementation hint

Content type: `archetype` (subtype: multiclass) with dedication feat and a list of available archetype feats. Character feat selection UI must filter available archetypes by dedication feat prerequisite. Multiclass dedication feats are class feats taken at 2nd level or higher. Each class in the core rulebook has a corresponding multiclass archetype.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Security AC exemption: Archetype content is static rulebook data; feat selection is character-scoped and server-validated.

## Roadmap section

- Roadmap: Core Rulebook
