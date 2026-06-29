# Acceptance Criteria — dc-cr-rock-runner

- Feature: Rock Runner (Dwarf Ancestry Feat)
- Release target: 20260412-dungeoncrawler-release-z
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-29

## Scope

Define Rock Runner as a level-1 dwarf ancestry-feat contract covering stone/earth terrain movement, narrow-surface balance benefits, and material-tag requirements in the tactical grid.

## Dependency checkpoints

- Depends on: dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-tactical-grid
- Merged into: dc-cr-dwarf-ancestry (all heritages and ancestry feats covered in bulk AC)

## Happy Path

- [ ] `[NEW]` Rock Runner exists as a level-1 dwarf ancestry feat.
- [ ] `[NEW]` Stone or earth rubble no longer imposes its normal movement penalty on a character with Rock Runner.
- [ ] `[NEW]` The character is not flat-footed when balancing on stone or earth narrow surfaces.
- [ ] `[NEW]` A successful Balance check on stone or earth upgrades to a critical success for the feat owner.

## Edge Cases

- [ ] `[NEW]` The feat only changes behavior on terrain or surfaces tagged as stone or earth; wood, metal, ice, and other materials remain baseline.
- [ ] `[NEW]` If the tactical grid omits a surface-material tag, balance and movement resolve with the default rules.
- [ ] `[NEW]` Only the feat owner receives the benefits; adjacent characters on the same tile do not.

## Failure Modes

- [ ] `[NEW]` Selecting the feat without a valid dwarf ancestry slot is rejected.
- [ ] `[NEW]` Unknown or malformed terrain tags do not crash movement/balance resolution.

## Security acceptance criteria

- Security AC exemption: ancestry-feat and terrain-resolution scope only; no new routes or input surfaces beyond existing feat assignment and movement handlers.
