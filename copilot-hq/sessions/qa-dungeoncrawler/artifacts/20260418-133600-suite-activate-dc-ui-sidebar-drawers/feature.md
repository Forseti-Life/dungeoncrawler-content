# Feature Brief: Sidebar Drawers and Docked Support Panels

- Work item id: dc-ui-sidebar-drawers
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: unscoped
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Depends on: dc-ui-map-first-player-shell
- Source: 2026-04-13 Dungeoncrawler UI architecture audit
- Category: ui-system
- Created: 2026-04-13

## Goal

Convert the current always-present side content into a drawer-based support system so character, inventory, quests, inspector, and chat remain accessible without permanently stealing board space. This keeps the scene primary while preserving the existing subsystems.

## Source reference

The current hexmap already has strong character/inventory/quest/chat functionality, but these panels compete with the board as equal peers. A drawer model better matches the intended tabletop feel and supports responsive play.

## Implementation hint

Retain the existing panel content and DOM hooks, but move them into a single-primary-drawer pattern on desktop and a modal/slide-over pattern on smaller screens. Persist the last-opened drawer and keep chat dock behavior distinct from the main right-side drawer.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: drawer refactor must not change authorization rules for character, inventory, quest, or chat data
- CSRF expectations: existing secured mutation endpoints remain unchanged
- Input validation: drawer/panel state stored client-side is presentation-only
- PII/logging constraints: no new personal data introduced; chat visibility rules remain enforced

## Roadmap section

- Roadmap: Dungeoncrawler UI modernization
