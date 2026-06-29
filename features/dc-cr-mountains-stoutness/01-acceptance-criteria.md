# Acceptance Criteria — dc-cr-mountains-stoutness

- Feature: Mountain's Stoutness (Dwarf Ancestry Feat)
- Release target: 20260412-dungeoncrawler-release-z
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-29

## Scope

Turn Mountain's Stoutness into a QA-ready level-9 ancestry-feat contract for the added max HP, modified recovery-check DC, and Toughness stacking interaction.

## Dependency checkpoints

- Depends on: dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-character-leveling, dc-cr-conditions
- Merged into: dc-cr-dwarf-ancestry (all heritages and ancestry feats covered in bulk AC)

## Happy Path

- [ ] `[NEW]` Mountain's Stoutness exists as a level-9 dwarf ancestry feat.
- [ ] `[NEW]` Selecting the feat adds the character's current level to maximum Hit Points.
- [ ] `[NEW]` While dying, the recovery-check DC becomes `9 + dying_value` instead of the baseline `10 + dying_value`.
- [ ] `[NEW]` If the character also has Toughness, the HP bonuses stack and the recovery-check DC becomes `6 + dying_value`.

## Edge Cases

- [ ] `[NEW]` Level changes recalculate the added max HP automatically.
- [ ] `[NEW]` Characters without Toughness still receive the Mountain's Stoutness recovery-check adjustment without any extra flags.
- [ ] `[NEW]` Retraining or removing the feat restores the baseline HP and recovery-check formulas.

## Failure Modes

- [ ] `[NEW]` Selecting the feat below level 9 or without a valid dwarf ancestry slot is rejected.
- [ ] `[NEW]` The feat never changes unrelated death-and-dying rules beyond the documented recovery-check DC adjustment.

## Security acceptance criteria

- Security AC exemption: ancestry-feat and character-state math scope only; no new routes or input surfaces beyond existing feat assignment and dying-state handlers.
