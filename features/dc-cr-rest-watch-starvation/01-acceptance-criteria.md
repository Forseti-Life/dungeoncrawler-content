# Acceptance Criteria: dc-cr-rest-watch-starvation

## Gap analysis reference
- DB sections: core/ch10/Resting and Daily Preparations (4 reqs)
- Depends on: dc-cr-conditions ✓, dc-cr-skill-system ✓

---

## Happy Path

### Watch Schedule
- [ ] `[NEW]` Watch duration by party size implemented; all party members share watch duty; minimum watch segments tracked.
- [ ] `[NEW]` Daily preparation sequence: rest → watch → prepare spells/abilities (10 min focus and preparation).

### Starvation and Thirst
- [ ] `[NEW]` Without water: immediate fatigue applied; after (Con modifier + 1) days, 1d4 damage per hour (cannot be healed until thirst quenched).
- [ ] `[NEW]` Without food: immediate fatigue applied; after (Con modifier + 1) days, 1 damage per day (cannot be healed until fed).
- [ ] `[NEW]` Starvation/thirst tracked as environmental hazards; triggers resolve at appropriate intervals.
- [ ] `[NEW]` Healing blocked until underlying condition (starvation/thirst) resolved.

---

## Edge Cases
- [ ] `[NEW]` Con modifier ≤ 0: minimum of 1 day before damage onset.
- [ ] `[NEW]` Simultaneously starving and dehydrated: both damage tracks active independently.

## Failure Modes
- [ ] `[TEST-ONLY]` Healing during active starvation: healing blocked until fed.
- [ ] `[TEST-ONLY]` Daily prep without completing rest: blocked or flagged (no spell restoration).

## Security acceptance criteria
- Security AC exemption: game-mechanic rest and survival logic; no new routes or user-facing input beyond existing exploration phase handlers
