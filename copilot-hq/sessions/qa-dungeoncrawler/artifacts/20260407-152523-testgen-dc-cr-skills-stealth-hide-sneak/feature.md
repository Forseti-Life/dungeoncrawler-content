# Feature Brief: Stealth — Hide, Sneak, Conceal Object

- Work item id: dc-cr-skills-stealth-hide-sneak
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
- DB sections: core/ch04/Stealth (Dex)
- Depends on: dc-cr-skill-system, dc-cr-skills-calculator-hardening

## Description
Implement Stealth (Dex) action handlers (REQs 1721–1730). High priority — detection
state infrastructure (Observed/Hidden/Undetected/Unnoticed) is FULLY implemented per
QA REQ 1715 PASS. Action handlers can build on this immediately with no new data model.

- **Hide** (1 action): Stealth vs Perception DC of all observers; on success becomes
  Hidden; must have cover or concealment to attempt; post-action detection state set
- **Sneak** (1 action): Stealth vs Perception DC; move up to half Speed while Hidden;
  become Observed if no cover/concealment at end of movement
- **Conceal an Object** (1 action): Stealth vs Perception DC of onlookers; on success
  object is not noticed
- **Move Quietly** (exploration): Stealth vs Perception DCs of potential observers;
  rolled once per relevant threat
- REQ 1723: post-action detection state reset based on cover/concealment availability
  (already modeled in entity_ref.detection_states + game_state.visibility)

## Security acceptance criteria

- Security AC exemption: game-mechanic skill action logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1721, 1722, 1723, 1724, 1725, 1726, 1727, 1728, 1729, 1730
- See `runbooks/roadmap-audit.md` for audit process.
