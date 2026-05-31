# Feature Brief: Ranger Class Mechanics

- Work item id: dc-cr-class-ranger
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release:
20260409-dungeoncrawler-release-e
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch03
- Category: class
- Created: 2026-04-07
- DB sections: core/ch03/Ranger
- Depends on: dc-cr-character-class, dc-cr-character-creation

## Goal

Implement Ranger class mechanics — Hunt Prey, Hunter's Edge (Flurry/Precision/Outwit), Animal Companion, Trackless Step, and Evasion — so players can designate and systematically eliminate targets with specialized combat and exploration advantages.

## Source reference

> "When you use Hunt Prey to designate a target, you gain a +2 circumstance bonus to Perception checks to Seek your prey and to Survival checks to Track your prey."

## Implementation hint

`HuntPreyAction` sets a `hunted_prey_id` on the character with a duration of until next daily prep or until a new prey is designated (only 1 at a time). `HunterEdgeService` applies the correct modifier based on edge selection: Flurry overrides MAP to -2 on second attack vs prey, Precision adds +1d8 on first hit vs prey per level tier, Outwit grants +2 AC vs prey. Animal Companion follows the same companion entity model as other classes; sync companion actions with ranger turn. Trackless Step suppresses ranger tracks in exploration mode.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; Hunt Prey designation only valid during the owning character's turn.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Prey target must be a valid encounter creature entity; Hunter's Edge enum restricted to [Flurry, Precision, Outwit]; level-based damage tier computed server-side.
- PII/logging constraints: no PII logged; log character_id, prey_target_id, hunter_edge; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
