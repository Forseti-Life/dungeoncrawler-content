# Feature Brief: Thievery Skill Actions

- Work item id: dc-cr-skills-thievery-disable-pick-lock
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
- DB sections: core/ch04/Thievery (Dex)
- Depends on: dc-cr-skill-system, dc-cr-hazards

## Description
Implement Thievery (Dex) skill action handlers (REQs 1747–1756). High priority —
Disable a Device and Pick a Lock are core dungeon interactions used constantly.
Stealth detection infrastructure (PASS REQ 1715) gives Head start for Palm Object/Steal.

- **Palm Object** (1 action, trained): Thievery vs Perception DC of observers; item
  concealed if success; Steal variant for worn/carried items
- **Steal** (1 action, trained): Thievery vs Perception DC; must be undetected or at
  least hidden; grabbed item removed from target inventory
- **Disable a Device** (2 actions, trained, thieves' tools): Thievery vs device DC;
  degrees; crit fail triggers trap if applicable; coordination with dc-cr-hazards
- **Pick a Lock** (2 actions, trained, thieves' tools or lockpick): Thievery vs lock DC;
  multiple successes for complex locks; broken pick on crit fail

## Security acceptance criteria

- Security AC exemption: game-mechanic skill action logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1747, 1748, 1749, 1750, 1751, 1752, 1753, 1754, 1755, 1756
- See `runbooks/roadmap-audit.md` for audit process.
