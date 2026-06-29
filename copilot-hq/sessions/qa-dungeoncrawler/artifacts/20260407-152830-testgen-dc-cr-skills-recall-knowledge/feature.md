# Feature Brief: Recall Knowledge Skill Action

- Work item id: dc-cr-skills-recall-knowledge
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: planned
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Occultism (Int), core/ch04/Religion (Wis)
- Depends on: dc-cr-skill-system, dc-cr-creature-identification, dc-cr-dc-rarity-spell-adjustment

## Description
Implement the Recall Knowledge skill action as a proper 1-action encounter handler
(REQs 1591–1594, 2329). Currently `recall_knowledge` is registered in
CanonicalActionRegistryService but routes to generic applyCharacterStateChanges
with no DC resolution or skill routing logic.

Required:
1. Wire recall_knowledge into EncounterPhaseHandler::processIntent() as a 1-action
   secret skill check
2. Route to correct skill based on topic (Arcana/Nature/Occultism/Religion/Society/
   Crafting/Medicine/Lore — see dc-cr-creature-identification for creature routing)
3. DC resolution: simple DC (GM-set by obscurity); level-based for creatures/hazards;
   rarity adjustment applied (see dc-cr-dc-rarity-spell-adjustment)
4. Degree-of-success outcomes: crit=info+bonus detail, success=info,
   fail=nothing, crit fail=false information

## Security acceptance criteria

- Security AC exemption: game-mechanic skill action logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- Book: core, Chapter: ch04, ch10
- REQs: 1591, 1592, 1593, 1594, 2329
- See `runbooks/roadmap-audit.md` for audit process.
