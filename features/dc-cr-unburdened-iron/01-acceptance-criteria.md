# Acceptance Criteria — dc-cr-unburdened-iron

- Feature: Unburdened Iron (Dwarf Ancestry Feat)
- Release target: 20260412-dungeoncrawler-release-z
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-29

## Scope

Turn Unburdened Iron into a QA-ready level-1 ancestry-feat contract for armor speed-penalty removal and the single-largest-other-penalty reduction rule.

## Dependency checkpoints

- Depends on: dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-equipment-system
- Merged into: dc-cr-dwarf-ancestry (all heritages and ancestry feats covered in bulk AC)

## Happy Path

- [ ] `[NEW]` Unburdened Iron exists as a level-1 dwarf ancestry feat.
- [ ] `[NEW]` Worn armor no longer applies its Speed penalty to a character with the feat.
- [ ] `[NEW]` The largest single other Speed penalty affecting the character is reduced by 5 feet.
- [ ] `[NEW]` Speed calculations remain deterministic when armor penalties and other penalties are combined.

## Edge Cases

- [ ] `[NEW]` Only the largest non-armor penalty is reduced; multiple non-armor penalties are not each reduced by 5 feet.
- [ ] `[NEW]` A character with no armor equipped still receives the largest-other-penalty reduction if one exists.
- [ ] `[NEW]` Speed can never become negative as a result of this adjustment logic.

## Failure Modes

- [ ] `[NEW]` Selecting the feat without a valid dwarf ancestry slot is rejected.
- [ ] `[NEW]` Malformed speed modifiers do not crash movement calculations; they surface a validation issue instead.

## Security acceptance criteria

- Security AC exemption: ancestry-feat and movement-math scope only; no new routes or input surfaces beyond existing feat assignment and movement handlers.
