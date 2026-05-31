# Acceptance Criteria: dc-cr-ceaseless-shadows

## Gap analysis reference
- DB sections: PF2E Core Rulebook (Fourth Printing), Halfling Ancestry Feats (Feat 13)
- Depends on: dc-cr-halfling-ancestry, dc-cr-ancestry-system, dc-cr-halfling-keen-eyes

---

## Happy Path

### Feat availability
- [ ] `[NEW]` Ceaseless Shadows appears as a selectable Halfling Feat 13 when the character is a halfling with Distracting Shadows.
- [ ] `[NEW]` Ceaseless Shadows requires and validates the Distracting Shadows prerequisite — characters without it cannot select this feat.

### Hide/Sneak without cover or concealment
- [ ] `[NEW]` A halfling with Ceaseless Shadows can use the Hide action without requiring cover or concealment.
- [ ] `[NEW]` A halfling with Ceaseless Shadows can use the Sneak action without requiring cover or concealment.
- [ ] `[NEW]` Characters without Ceaseless Shadows still require cover or concealment for Hide/Sneak (no regression).

### Upgraded creature cover
- [ ] `[NEW]` When creatures would grant lesser cover to the halfling, they instead grant full cover, and the halfling may Take Cover against those creatures.
- [ ] `[NEW]` When creatures already grant full cover to the halfling, that cover is upgraded to greater cover.
- [ ] `[NEW]` The upgraded cover tiers (lesser→full, full→greater) do not apply to characters without Ceaseless Shadows.

---

## Edge Cases
- [ ] `[NEW]` If a halfling has Distracting Shadows but not Ceaseless Shadows, Hide/Sneak still require cover or concealment.
- [ ] `[NEW]` Ceaseless Shadows creature-cover upgrade applies only to creature-granted cover (terrain cover is unaffected).

## Failure Modes
- [ ] `[TEST-ONLY]` Attempting to select Ceaseless Shadows without Distracting Shadows is blocked.
- [ ] `[TEST-ONLY]` Non-halfling characters cannot select or benefit from Ceaseless Shadows.

## Security acceptance criteria
- Security AC exemption: feat data and character-mechanic logic only; no new route surface beyond existing character/feat flows.
