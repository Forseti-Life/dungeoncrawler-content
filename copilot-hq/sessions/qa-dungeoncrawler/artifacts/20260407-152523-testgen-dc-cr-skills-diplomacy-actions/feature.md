# Feature Brief: Diplomacy and Intimidation Skill Actions

- Work item id: dc-cr-skills-diplomacy-actions
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
- DB sections: core/ch04/Diplomacy (Cha), core/ch04/Intimidation (Cha)
- Depends on: dc-cr-skill-system, dc-cr-gm-narrative-engine

## Description
Implement Diplomacy and Intimidation skill action handlers (REQs 1669–1683, 2327, 2330).

**Diplomacy** (REQs 1669–1677):
- Gather Information (exploration, secret, ~2 hr): ExplorationPhaseHandler handler;
  DC by availability (simple); crit fail = false info; NPC social DCs adjusted by
  attitude (friendly −2, helpful −5, unfriendly +2, hostile +5, opposed=incredibly hard)
- Make an Impression (exploration, ≥1 min, vs Will DC): rolls vs NPC Will DC;
  shifts NPC attitude on degrees (crit=+2 steps, success=+1, crit fail=−1 step)
- Request (requires Friendly/Helpful): attitude requirement enforcement
- Five NPC attitudes: Helpful → Friendly → Indifferent → Unfriendly → Hostile

**Intimidation** (REQs 1678–1683):
- Coerce (exploration, ≥1 min, vs Will DC): compliance window ≤1 day;
  crit fail = 1-week immunity from this character
- Demoralize (1 action, 30 ft, shared language): frightened 1/2 on success/crit;
  10-min immunity after attempt

Covers REQ 2327 (Gather Information DC) and REQ 2330 (NPC social DC by attitude).

## Security acceptance criteria

- Security AC exemption: game-mechanic skill action logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- Book: core, Chapter: ch04, ch10
- REQs: 1669, 1670, 1671, 1672, 1673, 1674, 1675, 1676, 1677,
         1678, 1679, 1680, 1681, 1682, 1683, 2327, 2330
- See `runbooks/roadmap-audit.md` for audit process.
