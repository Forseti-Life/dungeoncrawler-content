# Acceptance Criteria: dc-cr-halfling-ancestry

## Gap analysis reference
- DB sections: core/ch02 (Halfling ancestry, halfling heritages, halfling ancestry feats)
- Depends on: dc-cr-ancestry-system, dc-cr-halfling-keen-eyes

---

## Happy Path

### Character creation availability
- [ ] `[NEW]` Halfling appears as a selectable ancestry in character creation.

### Core ancestry stats
- [ ] `[NEW]` Halfling ancestry grants 6 HP, Small size, and 25-foot Speed.
- [ ] `[NEW]` Halfling ancestry grants Dexterity and Wisdom boosts plus the free-boost path supported by the build rules.

### Automatic ancestry traits
- [ ] `[NEW]` Halfling characters gain Halfling Luck.
- [ ] `[NEW]` Halfling characters gain Keen Eyes automatically.

### Ancestry integration
- [ ] `[NEW]` Halfling heritages and ancestry feats become available when Halfling is selected.
- [ ] `[NEW]` Halfling ancestry persists on the character sheet and downstream ancestry logic.

---

## Edge Cases
- [ ] `[NEW]` Keen Eyes is granted automatically and is not presented as an optional feat choice.
- [ ] `[NEW]` Switching away from Halfling ancestry removes halfling-only passive ancestry benefits on recalculation.

## Failure Modes
- [ ] `[TEST-ONLY]` Invalid ancestry-stat submissions are rejected and replaced with canonical halfling values.
- [ ] `[TEST-ONLY]` Halfling-only heritages and feats are blocked for non-halfling characters.

## Security acceptance criteria
- Security AC exemption: ancestry data modeling only; no new route surface beyond existing character flows
