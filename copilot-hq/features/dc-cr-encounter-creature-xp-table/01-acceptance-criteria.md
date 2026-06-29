# Acceptance Criteria: dc-cr-encounter-creature-xp-table

## Gap analysis reference
- DB sections: core/ch10/Encounter Building (5 reqs), core/ch10/Experience Points and Advancement (8 reqs partially — encounter-specific rows)
- Depends on: dc-cr-xp-award-system

---

## Happy Path

### Encounter Threat Tiers
- [ ] `[NEW]` Encounter threat tiers implemented: Trivial (≤40 XP), Low (60 XP), Moderate (80 XP), Severe (120 XP), Extreme (160 XP).
- [ ] `[NEW]` XP budget baseline is for 4 PCs; Character Adjustment value used for additional/missing PCs.

### Encounter Budget System
- [ ] `[NEW]` Encounter budget system built using creature XP cost table (relative level vs. party level → XP cost).
- [ ] `[NEW]` Creatures > 4 levels below party are trivial; creatures > 4 levels above are too dangerous (XP values not defined).
- [ ] `[NEW]` Party level drives all encounter budget calculations; level-variance handling supported.
- [ ] `[NEW]` PCs behind party level earn double XP until caught up.

### Creature XP Table
- [ ] `[NEW]` Creature XP by level-delta implemented:
  - Party Level –4: 10 XP
  - Party Level –3: 15 XP
  - Party Level –2: 20 XP
  - Party Level –1: 30 XP
  - Party Level: 40 XP
  - Party Level +1: 60 XP
  - Party Level +2: 80 XP
  - Party Level +3: 120 XP
  - Party Level +4: 160 XP
- [ ] `[NEW]` Hazard XP uses Table 10–14 (per dc-cr-hazards feature).

## Edge Cases
- [ ] `[NEW]` Party size of 1–3: Character Adjustment reduces XP budget proportionally.
- [ ] `[NEW]` Party size 5+: Character Adjustment increases budget.

## Failure Modes
- [ ] `[TEST-ONLY]` Creature level > party +4: no XP entry returned (not an error).
- [ ] `[TEST-ONLY]` Trivial encounter XP award: 0 XP (not an error state).

## Security acceptance criteria
- Security AC exemption: game-mechanic XP calculation; no new routes or user-facing input beyond existing encounter phase handlers
