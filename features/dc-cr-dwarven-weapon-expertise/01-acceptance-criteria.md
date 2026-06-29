# Acceptance Criteria — dc-cr-dwarven-weapon-expertise

- Feature: Dwarven Weapon Expertise
- Release target: 20260412-dungeoncrawler-release-z
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-29

## Scope

Turn Dwarven Weapon Expertise into a testable ancestry-feat contract for high-level proficiency propagation onto battle axes, picks, warhammers, and other trained dwarven weapons.

## Dependency checkpoints

- Depends on: dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-dwarven-weapon-familiarity, dc-cr-equipment-system

## Happy Path

- [ ] `[NEW]` The feat exists in the dwarf ancestry-feat catalog at level 13 with Dwarven Weapon Familiarity as a prerequisite.
- [ ] `[NEW]` When the character gains a class feature that grants expert or higher weapon proficiency, that rank is copied to battle axes, picks, warhammers, and any trained dwarven weapons.
- [ ] `[NEW]` The upgrade uses the character's current trained dwarven-weapon set rather than granting expertise to unrelated weapon families.
- [ ] `[NEW]` Rebuilds or later class-proficiency upgrades recalculate the dwarven-weapon expertise bonus correctly.

## Edge Cases

- [ ] `[NEW]` Characters without the prerequisite feat cannot select Dwarven Weapon Expertise.
- [ ] `[NEW]` If a weapon already has an equal or higher proficiency rank from another source, the feat does not downgrade or duplicate that rank.
- [ ] `[NEW]` New dwarven weapons learned later inherit the propagated proficiency if they satisfy the trained-weapon requirement.

## Failure Modes

- [ ] `[NEW]` Selecting the feat below level 13 or on a non-dwarf build fails validation.
- [ ] `[NEW]` Missing dwarven-weapon tags or malformed proficiency mappings do not crash the character sheet; they surface a validation defect instead.

## Security acceptance criteria

- Security AC exemption: ancestry-feat and proficiency-calculation scope only; no new routes or input surfaces beyond existing feat assignment and character build handlers.
