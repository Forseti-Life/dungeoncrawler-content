# Acceptance Criteria: dc-cr-xp-award-system

## Gap analysis reference
- DB sections: core/ch10/Experience Points and Advancement (8 reqs)
- Depends on: dc-cr-encounter-creature-xp-table

---

## Happy Path
- [ ] `[NEW]` XP threshold: 1,000 XP to gain a level; on leveling, subtract 1,000 XP and continue accumulation.
- [ ] `[NEW]` All party members receive the same XP award from any encounter or accomplishment.
- [ ] `[NEW]` Trivial encounters normally grant 0 XP (GM may award minor accomplishment XP).
- [ ] `[NEW]` Advancement speed variants: Fast (800 XP), Standard (1,000 XP), Slow (1,200 XP) — configurable.
- [ ] `[NEW]` Story-based leveling option: ignore XP entirely; level after ~3–4 sessions at major milestones.
- [ ] `[NEW]` Accomplishment XP table implemented: minor/moderate/major accomplishments grant defined XP.
- [ ] `[NEW]` Moderate and major accomplishments: system flags for Hero Point award to an instrumental PC.
- [ ] `[NEW]` Creature XP uses Table 10–2 (dc-cr-encounter-creature-xp-table); Hazard XP uses Table 10–14.

## Edge Cases
- [ ] `[NEW]` XP award at level-up: excess XP carries over (no reset to 0).
- [ ] `[NEW]` PCs behind party level: double XP until caught up.

## Failure Modes
- [ ] `[TEST-ONLY]` Story-based leveling active: XP not tracked (not an error).
- [ ] `[TEST-ONLY]` Trivial encounter: 0 XP returned (not an error state).

## Security acceptance criteria
- Security AC exemption: game-mechanic XP/progression logic; no new routes or user-facing input beyond existing encounter and advancement handlers
