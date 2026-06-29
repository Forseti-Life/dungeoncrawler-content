# Feature Brief: Creature Identification (Recall Knowledge Routing)

- Work item id: dc-cr-creature-identification
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: planned
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch10
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: core/ch10/Creature Identification
- Depends on: dc-cr-skill-system, dc-cr-dc-rarity-spell-adjustment

## Description
Implement creature-trait → Recall Knowledge skill routing (REQ 2331). The
`recall_knowledge` action is registered in CanonicalActionRegistryService but routes
to generic applyCharacterStateChanges with no DC resolution or skill selection.

Routing table to implement:
- Aberration, Ooze, Undead → Occultism
- Animal, Beast, Fungus, Plant → Nature
- Construct, Dragon, Elemental → Arcana
- Celestial, Fiend → Religion
- Humanoid → Society
- Other → GM discretion

Also wire DC resolution: simple DC based on general recall; level-based for
creatures/hazards; rarity adjustment applied. Covers REQs 2329 and 2331.

## Security acceptance criteria

- Security AC exemption: game-mechanic logic; no new routes or user-facing input beyond existing character creation and encounter phase forms

## Roadmap section
- Book: core, Chapter: ch10
- REQs: 2329, 2331
- See `runbooks/roadmap-audit.md` for audit process.
