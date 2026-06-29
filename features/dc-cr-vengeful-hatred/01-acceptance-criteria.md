# Acceptance Criteria — dc-cr-vengeful-hatred

- Feature: Vengeful Hatred (Dwarf Ancestry Feat)
- Release target: 20260412-dungeoncrawler-release-z
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-29

## Scope

Define Vengeful Hatred as a QA-ready level-1 dwarf ancestry-feat contract for ancestry-foe selection, damage-bonus scaling by weapon dice, and the temporary retaliation bonus after taking a critical hit.

## Dependency checkpoints

- Depends on: dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-ancestry-traits
- Merged into: dc-cr-dwarf-ancestry (all heritages and ancestry feats covered in bulk AC)

## Happy Path

- [ ] `[NEW]` Vengeful Hatred exists as a level-1 dwarf ancestry feat and prompts the player to choose one ancestral foe type from drow, duergar, giant, or orc.
- [ ] `[NEW]` The chosen foe type grants a +1 circumstance bonus to weapon and unarmed damage against that foe, scaling by the number of weapon damage dice at higher levels.
- [ ] `[NEW]` If a creature critically hits the character and deals damage, the character gains the same damage bonus against that specific creature for 1 minute even if it is not the chosen ancestral foe type.
- [ ] `[NEW]` The chosen foe type and any active temporary retaliation target are visible in character/combat state for QA verification.

## Edge Cases

- [ ] `[NEW]` Changing the chosen ancestral foe requires a retrain/rebuild flow rather than an in-combat toggle.
- [ ] `[NEW]` Damage scaling updates when the weapon's number of damage dice increases.
- [ ] `[NEW]` The temporary retaliation bonus expires after 1 minute and does not persist between encounters unless the timer is refreshed by another triggering critical hit.

## Failure Modes

- [ ] `[NEW]` Invalid ancestral foe choices are rejected during feat selection.
- [ ] `[NEW]` A critical hit that deals no damage does not grant the temporary retaliation bonus.

## Security acceptance criteria

- Security AC exemption: ancestry-feat and combat-modifier scope only; no new routes or input surfaces beyond existing feat assignment and combat-resolution handlers.
