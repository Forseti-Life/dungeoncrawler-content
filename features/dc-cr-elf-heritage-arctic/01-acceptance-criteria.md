# Acceptance Criteria — dc-cr-elf-heritage-arctic

- Feature: Arctic Elf Heritage
- Release target: 20260412-dungeoncrawler-release-z
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-29

## Scope

Make Arctic Elf a QA-ready heritage contract with level-scaling cold resistance and one-step environmental cold mitigation so the missing implementation gaps can be tested directly.

## Dependency checkpoints

- Depends on: dc-cr-elf-ancestry, dc-cr-heritage-system

## Happy Path

- [ ] `[NEW]` Arctic Elf is present as an elf-only heritage option.
- [ ] `[NEW]` Selecting Arctic Elf grants cold resistance equal to half the character level, minimum 1.
- [ ] `[NEW]` Environmental cold effects are treated as one step less severe for the character.
- [ ] `[NEW]` The cold-resistance value recalculates when the character level changes.

## Edge Cases

- [ ] `[NEW]` Level 1 characters still receive the minimum cold resistance of 1.
- [ ] `[NEW]` Only cold/environmental-cold effects are downgraded; unrelated environmental hazards stay unchanged.
- [ ] `[NEW]` One-step severity downgrades follow the documented ladder without skipping directly to harmless.

## Failure Modes

- [ ] `[NEW]` Non-elf characters cannot select Arctic Elf heritage.
- [ ] `[NEW]` If an environmental hazard lacks cold-severity metadata, the hazard resolves normally instead of producing an implementation error.

## Security acceptance criteria

- Security AC exemption: passive ancestry heritage behavior only; no new routes or input surfaces beyond existing heritage assignment, resistance, and hazard-resolution handlers.
