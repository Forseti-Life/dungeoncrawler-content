# Acceptance Criteria — dc-cr-dwarf-heritage-forge

- Feature: Dwarf Heritage — Forge Dwarf
- Release target: 20260412-dungeoncrawler-release-z
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-29

## Scope

Define Forge Dwarf as a QA-ready heritage contract with level-scaling fire resistance and environmental heat mitigation so both combat damage and exploration hazards can be validated against the same rules.

## Dependency checkpoints

- Depends on: dc-cr-dwarf-ancestry, dc-cr-heritage-system
- Merged into: dc-cr-dwarf-ancestry (all heritages and ancestry feats covered in bulk AC)

## Happy Path

- [ ] `[NEW]` Forge Dwarf is available only under the dwarf ancestry heritage list.
- [ ] `[NEW]` Selecting Forge Dwarf grants fire resistance equal to half the character level, with a minimum of 1.
- [ ] `[NEW]` Environmental heat effects are treated as one step less severe for a Forge Dwarf character.
- [ ] `[NEW]` The fire-resistance value recalculates automatically when the character level changes.

## Edge Cases

- [ ] `[NEW]` Level 1 characters still receive the minimum fire resistance of 1.
- [ ] `[NEW]` Environmental heat downgrades follow the documented one-step ladder and do not skip multiple severity bands.
- [ ] `[NEW]` Non-fire damage and non-heat environmental effects are unaffected by the heritage.

## Failure Modes

- [ ] `[NEW]` Selecting Forge Dwarf on a non-dwarf character returns a validation error.
- [ ] `[NEW]` If an environmental hazard lacks a heat severity tag, the hazard resolves normally instead of crashing the encounter flow.

## Security acceptance criteria

- Security AC exemption: passive ancestry heritage behavior only; no new routes or input surfaces beyond existing heritage assignment, resistance, and hazard-resolution handlers.
