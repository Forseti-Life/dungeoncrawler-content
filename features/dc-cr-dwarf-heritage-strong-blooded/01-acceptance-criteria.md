# Acceptance Criteria — dc-cr-dwarf-heritage-strong-blooded

- Feature: Dwarf Heritage — Strong-Blooded Dwarf
- Release target: 20260412-dungeoncrawler-release-z
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-29

## Scope

Define the Strong-Blooded dwarf heritage contract so poison resistance and poison-stage reduction rules can be validated in the affliction engine without ambiguity.

## Dependency checkpoints

- Depends on: dc-cr-dwarf-ancestry, dc-cr-heritage-system
- Merged into: dc-cr-dwarf-ancestry (all heritages and ancestry feats covered in bulk AC)

## Happy Path

- [ ] `[NEW]` Strong-Blooded is available as a dwarf-only heritage selection.
- [ ] `[NEW]` The heritage grants poison resistance equal to half the character level, minimum 1.
- [ ] `[NEW]` On a successful save against a poison affliction, the poison stage is reduced by 2, or by 1 if the poison is virulent.
- [ ] `[NEW]` On a critical success, the poison stage is reduced by 3, or by 2 if the poison is virulent.

## Edge Cases

- [ ] `[NEW]` Level-up recalculates the poison-resistance value without requiring the heritage to be re-selected.
- [ ] `[NEW]` Non-poison afflictions such as disease do not receive the Strong-Blooded stage-reduction benefit.
- [ ] `[NEW]` Virulent-poison handling still uses the reduced stage-drop values rather than the standard success/critical-success drops.

## Failure Modes

- [ ] `[NEW]` Selecting the heritage for a non-dwarf ancestry is rejected.
- [ ] `[NEW]` If the affliction is missing poison metadata, resolution falls back safely instead of applying the Strong-Blooded adjustment incorrectly.

## Security acceptance criteria

- Security AC exemption: passive ancestry heritage behavior only; no new routes or input surfaces beyond existing heritage assignment and affliction-resolution handlers.
