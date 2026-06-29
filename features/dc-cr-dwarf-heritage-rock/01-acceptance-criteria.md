# Acceptance Criteria — dc-cr-dwarf-heritage-rock

- Feature: Dwarf Heritage — Rock Dwarf
- Release target: 20260412-dungeoncrawler-release-z
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-29

## Scope

Capture Rock Dwarf as a heritage contract for anti-displacement combat rules, including the defense bonus against Shove/Trip/knock-prone effects and the forced-movement reduction behavior.

## Dependency checkpoints

- Depends on: dc-cr-dwarf-ancestry, dc-cr-heritage-system
- Merged into: dc-cr-dwarf-ancestry (all heritages and ancestry feats covered in bulk AC)

## Happy Path

- [ ] `[NEW]` Rock Dwarf is selectable only for dwarf characters within the heritage system.
- [ ] `[NEW]` The heritage grants a +2 circumstance bonus to the relevant Fortitude or Reflex DC / save checks against Shove, Trip, and knock-prone effects.
- [ ] `[NEW]` Forced movement affecting the character is reduced by half when the pushed or pulled distance is 10 feet or more.
- [ ] `[NEW]` The passive applies automatically during maneuver resolution without any manual toggle.

## Edge Cases

- [ ] `[NEW]` Voluntary movement is never halved by the heritage.
- [ ] `[NEW]` Small forced movements below the threshold stay at their normal distance unless the movement engine already rounds them under existing rules.
- [ ] `[NEW]` The bonus applies only to the targeted anti-displacement effects and not to unrelated Reflex or Fortitude saves.

## Failure Modes

- [ ] `[NEW]` Invalid ancestry/heritage combinations are rejected.
- [ ] `[NEW]` Combat resolution falls back to the normal forced-movement rules if the action is not tagged as Shove, Trip, knock-prone, or forced movement.

## Security acceptance criteria

- Security AC exemption: passive ancestry heritage behavior only; no new routes or input surfaces beyond existing heritage assignment and combat-resolution handlers.
