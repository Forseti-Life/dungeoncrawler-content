# Feature Brief: Survival — Sense Direction, Track, Cover Tracks

- Work item id: dc-cr-skills-survival-track-direction
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
- DB sections: core/ch04/Survival (Wis)
- Depends on: dc-cr-skill-system, dc-cr-exploration-mode

## Description
Implement Survival (Wis) action handlers and the Subsist general skill action
(REQs 1572–1573, 1595–1598, 1739–1746).

- **Subsist** (downtime, untrained): Nature (wilderness) or Society (urban) vs DC 15
  (higher in harsh environments; +2 DC per additional creature fed).
  Crit Success = full provisions for group; Success = self fed; Fail = meager;
  Crit Fail = starvation begins.
- **Sense Direction** (exploration, untrained, 1 min): Survival vs DC 15 (trackless) or 10
  (open terrain); gives compass direction or landmark orientation
- **Track** (exploration, trained): Survival vs DC based on trail freshness + terrain;
  follow tracks at half Speed; losing trail resets
- **Cover Tracks** (exploration, trained): Survival vs trackers' Survival; on success
  trackers take DC penalty to follow
- REQs 1572–1573 define the general skill action framework (untrained vs trained gating);
  covered here as Subsist is the primary untrained general action.

## Security acceptance criteria

- Security AC exemption: game-mechanic skill action logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1572, 1573, 1595, 1596, 1597, 1598, 1739, 1740, 1741, 1742, 1743, 1744, 1745, 1746
- See `runbooks/roadmap-audit.md` for audit process.
