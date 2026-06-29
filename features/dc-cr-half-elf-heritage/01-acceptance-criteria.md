# Acceptance Criteria — dc-cr-half-elf-heritage

- Feature: Half-Elf Heritage
- Release target: 20260412-dungeoncrawler-release-z
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-29

## Scope

Define Half-Elf heritage as a Human heritage overlay contract covering trait grants, low-light vision, and the expanded ancestry-feat pool used at later feat milestones.

## Dependency checkpoints

- Depends on: dc-cr-human-ancestry, dc-cr-heritage-system, dc-cr-low-light-vision, dc-cr-ancestry-feat-schedule

## Happy Path

- [ ] `[NEW]` Half-Elf is implemented as a selectable Human heritage rather than a standalone ancestry.
- [ ] `[NEW]` Selecting the heritage grants the elf trait, the half-elf trait, and low-light vision.
- [ ] `[NEW]` Ancestry-feat selection for a Half-Elf character can draw from human, elf, and half-elf feat pools while still enforcing feat prerequisites.
- [ ] `[NEW]` The expanded feat-pool behavior is visible anywhere the character gains an ancestry feat slot.

## Edge Cases

- [ ] `[NEW]` If the character already has low-light vision from another valid source, the heritage does not create duplicate sense flags.
- [ ] `[NEW]` Half-Elf remains mutually exclusive with other Human heritages.
- [ ] `[NEW]` Feat browsing clearly indicates why an elf, half-elf, or human feat is or is not selectable for the current character.

## Failure Modes

- [ ] `[NEW]` Non-Human characters cannot select the Half-Elf heritage.
- [ ] `[NEW]` The feat picker rejects ancestry feats outside the allowed human/elf/half-elf pools instead of silently accepting them.

## Security acceptance criteria

- Security AC exemption: ancestry heritage and feat-eligibility scope only; no new routes or input surfaces beyond existing heritage assignment and ancestry-feat handlers.
