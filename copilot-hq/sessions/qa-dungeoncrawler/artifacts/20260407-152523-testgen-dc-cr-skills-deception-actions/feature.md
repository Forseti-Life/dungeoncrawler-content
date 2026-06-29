# Feature Brief: Deception Skill Actions

- Work item id: dc-cr-skills-deception-actions
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
- DB sections: core/ch04/Deception (Cha)
- Depends on: dc-cr-skill-system, dc-cr-conditions

## Description
Implement Deception (Cha) skill action handlers (REQs 1658–1668).

- **Create a Diversion** (1 action): Deception vs Perception of all observers; on success,
  character becomes Hidden from all who fail
- **Impersonate** (exploration, trained): Deception vs Perception of observers; requires
  disguise; crit fail reveals true identity
- **Lie** (secret check vs Sense Motive): failure gives target +4 circumstance bonus
  to resist future lies this conversation; delayed recheck on contradicting evidence
- **Feint** (1 action, mental, trained, melee range): Deception vs target Perception DC;
  crit success=flat-footed whole turn, success=flat-footed vs next attack,
  crit fail=attacker becomes flat-footed

## Security acceptance criteria

- Security AC exemption: game-mechanic skill action logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1658, 1659, 1660, 1661, 1662, 1663, 1664, 1665, 1666, 1667, 1668
- See `runbooks/roadmap-audit.md` for audit process.
