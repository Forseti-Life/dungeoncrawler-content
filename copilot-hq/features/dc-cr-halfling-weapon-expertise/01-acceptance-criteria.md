# Acceptance Criteria: dc-cr-halfling-weapon-expertise

## Gap analysis reference
- DB sections: PF2E Core Rulebook (Fourth Printing), Halfling Ancestry Feats (Feat 13)
- Depends on: dc-cr-halfling-ancestry, dc-cr-ancestry-system, dc-cr-halfling-weapon-expertise (feat), dc-cr-dwarven-weapon-familiarity (pattern reference)

---

## Happy Path

### Feat availability
- [ ] `[NEW]` Halfling Weapon Expertise appears as a selectable Halfling Feat 13 when the character is a halfling with Halfling Weapon Familiarity.
- [ ] `[NEW]` Halfling Weapon Expertise requires and validates the Halfling Weapon Familiarity prerequisite — characters without it cannot select this feat.

### Proficiency cascade on class weapon advancement
- [ ] `[NEW]` When the character's class grants expert proficiency in a weapon group, the character also gains expert proficiency in: sling, halfling sling staff, shortsword, and any halfling weapons they are already trained in.
- [ ] `[NEW]` When the character's class grants master (or greater) proficiency in a weapon group, the same cascade applies — proficiency in the halfling weapon set advances to match.
- [ ] `[NEW]` Only weapons the character is already trained in receive the cascade; untrained halfling weapons are not upgraded.
- [ ] `[NEW]` Characters without Halfling Weapon Expertise receive no such cascade (no regression).

### Specific weapon coverage
- [ ] `[NEW]` Sling is included in the cascade set.
- [ ] `[NEW]` Halfling sling staff is included in the cascade set.
- [ ] `[NEW]` Shortsword is included in the cascade set.
- [ ] `[NEW]` All halfling weapons (per game data tagging) are included in the cascade set, limited to those the character is trained in.

---

## Edge Cases
- [ ] `[NEW]` Cascade fires on every class proficiency advancement event (not just on feat selection).
- [ ] `[NEW]` If the character is already at the cascaded proficiency rank for a weapon, no downgrade occurs.

## Failure Modes
- [ ] `[TEST-ONLY]` Attempting to select Halfling Weapon Expertise without Halfling Weapon Familiarity is blocked.
- [ ] `[TEST-ONLY]` Non-halfling characters cannot select this feat.
- [ ] `[TEST-ONLY]` Untrained halfling weapons are not upgraded when the cascade fires.

## Security acceptance criteria
- Security AC exemption: weapon proficiency cascade logic only; no new route surface beyond existing character/proficiency flows.
