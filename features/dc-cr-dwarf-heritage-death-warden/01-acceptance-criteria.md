# Acceptance Criteria — dc-cr-dwarf-heritage-death-warden

- Feature: Dwarf Heritage — Death Warden
- Release target: 20260412-dungeoncrawler-release-z
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-29

## Scope

Turn the Death Warden dwarf heritage into a testable contract covering heritage availability, necromancy save upgrades, and the boundaries of the passive so it can be implemented inside the save-resolution pipeline.

## Dependency checkpoints

- Depends on: dc-cr-dwarf-ancestry, dc-cr-heritage-system
- Merged into: dc-cr-dwarf-ancestry (all heritages and ancestry feats covered in bulk AC)

## Happy Path

- [ ] `[NEW]` The Death Warden heritage exists as a selectable dwarf heritage and is unavailable to non-dwarf ancestries.
- [ ] `[NEW]` When a Death Warden dwarf succeeds on a saving throw against a necromancy effect, the final result is upgraded to a critical success.
- [ ] `[NEW]` Necromancy critical successes remain critical successes rather than being double-upgraded or otherwise altered.
- [ ] `[NEW]` The heritage effect is passive and automatic; no extra player action or toggle is required during save resolution.

## Edge Cases

- [ ] `[NEW]` The save upgrade only applies to effects tagged as necromancy and does not modify non-necromancy saves.
- [ ] `[NEW]` Characters can hold only one dwarf heritage at a time, so Death Warden cannot stack with another dwarf heritage bonus.
- [ ] `[NEW]` Save logs or combat resolution output clearly show the upgraded outcome for QA traceability.

## Failure Modes

- [ ] `[NEW]` Invalid heritage selection for the wrong ancestry is rejected rather than persisted.
- [ ] `[NEW]` If an effect lacks the necromancy tag, the save resolver falls back to the baseline success result without throwing an error.

## Security acceptance criteria

- Security AC exemption: passive ancestry heritage behavior only; no new routes or input surfaces beyond existing heritage assignment and combat-resolution handlers.
