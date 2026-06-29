# Feature Brief: Lore and Earn Income Skill Actions

- Work item id: dc-cr-skills-lore-earn-income
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: planned
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Lore (Int)
- Depends on: dc-cr-skill-system, dc-cr-economy, dc-cr-dc-rarity-spell-adjustment

## Description
Complete Lore skill and Earn Income downtime action (REQs 1684–1687, 1579–1582, 2326).

**Lore (complete)** (REQs 1685–1687):
- Breadth enforcement: Lore is narrow topic only (no "all knowledge" subtopics)
- Best-modifier selection: when character has multiple Lore subtypes, caller must
  iterate to find best applicable modifier (wire into CharacterCalculator)
- Earn Income via Lore: Lore specialization can be used for Earn Income at downtime

**Earn Income** (downtime, trained — REQs 1579–1582, 2326):
- DowntimePhaseHandler currently has a stub; wire full logic
- Task level cap = character level
- DC = task level via Table 10-5 (Earn Income DC — REQ 2326)
- Income rate table: task level × degree of success (PF2e Table 4-2)
- Applicable skills: Arcana, Crafting, Lore, Occultism, Performance, Religion, Society

## Security acceptance criteria

- Security AC exemption: game-mechanic skill action logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- Book: core, Chapter: ch04, ch10
- REQs: 1579, 1580, 1581, 1582, 1685, 1686, 1687, 2326
- See `runbooks/roadmap-audit.md` for audit process.
