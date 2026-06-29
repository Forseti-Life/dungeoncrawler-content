# Feature Brief: Performance Skill Actions

- Work item id: dc-cr-skills-performance-perform
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Performance (Cha)
- Depends on: dc-cr-skill-system

## Description
Implement Performance (Cha) skill actions (REQs 1716–1720).

- **Perform** (1 action, auditory or visual trait by medium): Performance vs audience DC;
  degrees affect audience reaction; used in encounter (bardic performance) and exploration
- Trait system: Performance actions have `auditory` or `visual` trait depending on medium
  (singing=auditory, dance=visual, etc.)
- Audience DC table: unfamiliar crowd=15, local=20, regional=30 (approximate)
- Earn Income via Performance: coordination with dc-cr-skills-lore-earn-income

## Security acceptance criteria

- Security AC exemption: game-mechanic skill action logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1716, 1717, 1718, 1719, 1720
- See `runbooks/roadmap-audit.md` for audit process.
