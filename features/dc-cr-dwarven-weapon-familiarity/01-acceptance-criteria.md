# Acceptance Criteria — dc-cr-dwarven-weapon-familiarity

- Feature: Dwarven Weapon Familiarity (Ancestry Feat)
- Release target: 20260412-dungeoncrawler-release-z
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-29

## Scope

Define Dwarven Weapon Familiarity as a level-1 ancestry-feat contract covering the granted proficiencies, uncommon dwarven-weapon access, and weapon-category remapping rules.

## Dependency checkpoints

- Depends on: dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-equipment-system
- Merged into: dc-cr-dwarf-ancestry (all heritages and ancestry feats covered in bulk AC)

## Happy Path

- [ ] `[NEW]` The feat exists as a level-1 dwarf ancestry feat and is only available through a valid ancestry-feat slot.
- [ ] `[NEW]` Selecting the feat grants trained proficiency with battle axe, pick, and warhammer.
- [ ] `[NEW]` Uncommon dwarf weapons become available to the character once the feat is selected.
- [ ] `[NEW]` Martial dwarf weapons count as simple and advanced dwarf weapons count as martial for this character's proficiency calculations.

## Edge Cases

- [ ] `[NEW]` Non-dwarf characters and characters without an open ancestry-feat slot cannot select the feat.
- [ ] `[NEW]` If the character later gains broader proficiency from class progression, the familiarity remapping still resolves correctly.
- [ ] `[NEW]` Removing or retraining the feat restores the baseline weapon-access rules.

## Failure Modes

- [ ] `[NEW]` Malformed or non-dwarf weapon tags are rejected during content validation.
- [ ] `[NEW]` The proficiency remapping never exposes unrelated uncommon weapons outside the dwarf weapon group.

## Security acceptance criteria

- Security AC exemption: ancestry-feat and proficiency-calculation scope only; no new routes or input surfaces beyond existing feat assignment and character build handlers.
