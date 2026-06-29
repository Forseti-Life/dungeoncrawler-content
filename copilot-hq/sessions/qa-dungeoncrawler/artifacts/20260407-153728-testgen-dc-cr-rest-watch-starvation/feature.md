# Feature Brief: Resting, Watch Schedule, and Starvation/Thirst

- Work item id: dc-cr-rest-watch-starvation
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: planned
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch10
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: core/ch10/Resting and Daily Preparations
- Depends on: dc-cr-encounter-rules, dc-cr-conditions

## Description
Extend DowntimePhaseHandler long_rest/downtime_rest with watch scheduling and
food/water deprivation tracking (REQs 2346–2349).

Current state: processLongRest() restores HP and spell slots. No watch tracking,
no food/water counters, no deprivation damage.

Required:
1. **Watch schedule** (REQ 2346): compute watch duration by party size (3 PCs = one 8-hour
   watch; 4+ PCs = shorter proportional watches); track which PCs are on watch
2. **Starvation tracking** (REQ 2349): game_state tracks `food_days` counter; without food:
   immediate fatigued condition; after Con_mod+1 days: 1 damage/day, unhealable until fed
3. **Thirst tracking** (REQ 2348): game_state tracks `water_hours` counter; without water:
   immediate fatigued condition; after Con_mod+1 days: 1d4 damage/hour, unhealable until quenched
4. **AdvanceDay hook**: DowntimePhaseHandler::processAdvanceDay() must decrement food/water
   counters and apply fatigue/damage as thresholds are crossed

## Security acceptance criteria

- Security AC exemption: game-mechanic logic; no new routes or user-facing input beyond existing character creation and encounter phase forms

## Roadmap section
- Book: core, Chapter: ch10
- REQs: 2346, 2347, 2348, 2349
- See `runbooks/roadmap-audit.md` for audit process.
