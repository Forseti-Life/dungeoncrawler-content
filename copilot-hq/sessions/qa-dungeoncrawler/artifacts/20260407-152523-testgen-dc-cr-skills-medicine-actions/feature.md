# Feature Brief: Medicine Skill Actions

- Work item id: dc-cr-skills-medicine-actions
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
- DB sections: core/ch04/Medicine (Wis)
- Depends on: dc-cr-skill-system, dc-cr-conditions, dc-cr-encounter-rules

## Description
Implement Medicine (Wis) skill action handlers (REQs 1691–1704). High priority —
Administer First Aid and Treat Wounds are core survival actions.

Note: HPManager::stabilizeCharacter() exists but is wired to Hero Point heroic-recovery,
NOT to a Medicine skill check. The first-aid action handler is absent.

- **Administer First Aid** (2 actions, trained, healer's tools): Stabilize (DC 15 Flat Check
  removed; Medicine vs DC 15) or Stop Bleeding (DC varies); degrees affect outcome
- **Treat Disease** (downtime, trained, healer's tools): Medicine vs disease DC; sets
  save bonus for upcoming disease save
- **Treat Poison** (1 action, trained, healer's tools): Medicine vs poison DC; gives
  bonus to immediate saving throw against poison
- **Treat Wounds** (exploration, trained, healer's tools, 10 min): Medicine vs DC 15 (or
  20 master, 30 legendary); restores HP on success; 1-hour re-treat restriction

## Security acceptance criteria

- Security AC exemption: game-mechanic skill action logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1691, 1692, 1693, 1694, 1695, 1696, 1697, 1698, 1699, 1700, 1701, 1702, 1703, 1704
- See `runbooks/roadmap-audit.md` for audit process.
