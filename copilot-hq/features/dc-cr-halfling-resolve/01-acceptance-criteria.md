# Acceptance Criteria: dc-cr-halfling-resolve

## Gap analysis reference
- DB sections: PF2E Core Rulebook (Fourth Printing), Halfling Ancestry Feats (Feat 9)
- Depends on: dc-cr-halfling-ancestry, dc-cr-ancestry-system, dc-cr-halfling-heritage-gutsy

---

## Happy Path

### Feat availability
- [ ] `[NEW]` Halfling Resolve appears as a selectable Halfling Feat 9 when the character is a halfling.
- [ ] `[NEW]` No prerequisite feat is required beyond halfling ancestry.

### Emotion saving throw upgrade (all halflings with feat)
- [ ] `[NEW]` When a halfling with Halfling Resolve rolls a success on a saving throw against an emotion effect, the result is upgraded to a critical success.
- [ ] `[NEW]` Other saving throw outcomes (failure, critical failure, critical success) are not altered by this upgrade rule.
- [ ] `[NEW]` Non-halfling characters and halflings without this feat receive no saving throw upgrade (no regression).

### Gutsy Halfling critical failure mitigation
- [ ] `[NEW]` When a halfling with Halfling Resolve AND the Gutsy Halfling heritage rolls a critical failure on a saving throw against an emotion effect, the result is treated as a failure instead.
- [ ] `[NEW]` Halflings with Halfling Resolve but WITHOUT Gutsy Halfling do not receive the critical-failure-to-failure mitigation.

---

## Edge Cases
- [ ] `[NEW]` Both Halfling Resolve effects (success upgrade and Gutsy critical-failure mitigation) apply simultaneously when both conditions are met.
- [ ] `[NEW]` Emotion-effect identification is correctly scoped — non-emotion saving throws are not affected.

## Failure Modes
- [ ] `[TEST-ONLY]` Attempting to select Halfling Resolve on a non-halfling character is blocked.
- [ ] `[TEST-ONLY]` The Gutsy mitigation does not fire when the Gutsy Halfling heritage is absent.

## Security acceptance criteria
- Security AC exemption: saving throw mechanic logic only; no new route surface beyond existing character/combat flows.
