# Test Plan: dc-cr-vengeful-hatred

## Coverage summary
- AC items: 9 (4 happy path, 3 edge cases, 2 failure modes)
- Test cases: 5 (TC-VHT-01-05)
- Suites: playwright (feat progression, encounter damage, duration tracking)
- Security: Security AC exemption: ancestry-feat and combat-modifier scope only; no new routes or input surfaces beyond existing feat assignment and combat-resolution handlers.

---

## TC-VHT-01 — Feat availability and prerequisite gating
- Description: Vengeful Hatred exists as a level-1 dwarf ancestry feat and prompts the player to choose one ancestral foe type from drow, duergar, giant, or orc.
- Suite: playwright/feat-progression
- Expected: Vengeful Hatred exists as a level-1 dwarf ancestry feat and prompts the player to choose one ancestral foe type from drow, duergar, giant, or orc.
- AC: Happy Path-1

## TC-VHT-02 — Primary granted benefit application
- Description: The chosen foe type grants a +1 circumstance bonus to weapon and unarmed damage against that foe, scaling by the number of weapon damage dice at higher levels.
- Suite: playwright/encounter
- Expected: The chosen foe type grants a +1 circumstance bonus to weapon and unarmed damage against that foe, scaling by the number of weapon damage dice at higher levels.; If a creature critically hits the character and deals damage, the character gains the same damage bonus against that specific creature for 1 minute even if it is not the chosen ancestral foe type.
- AC: Happy Path-2, Happy Path-3

## TC-VHT-03 — Recalculation, retraining, and later progression behavior
- Description: If a creature critically hits the character and deals damage, the character gains the same damage bonus against that specific creature for 1 minute even if it is not the chosen ancestral foe type.
- Suite: playwright/encounter
- Expected: If a creature critically hits the character and deals damage, the character gains the same damage bonus against that specific creature for 1 minute even if it is not the chosen ancestral foe type.; The chosen foe type and any active temporary retaliation target are visible in character/combat state for QA verification.
- AC: Happy Path-3, Happy Path-4

## TC-VHT-04 — Edge-case rules interaction coverage
- Description: Changing the chosen ancestral foe requires a retrain/rebuild flow rather than an in-combat toggle.
- Suite: playwright/encounter
- Expected: Changing the chosen ancestral foe requires a retrain/rebuild flow rather than an in-combat toggle.; Damage scaling updates when the weapon's number of damage dice increases.; The temporary retaliation bonus expires after 1 minute and does not persist between encounters unless the timer is refreshed by another triggering critical hit.
- AC: Edge Cases-1, Edge Cases-2, Edge Cases-3

## TC-VHT-05 — Validation errors and malformed-data handling
- Description: Invalid ancestral foe choices are rejected during feat selection.
- Suite: playwright/encounter
- Expected: Invalid ancestral foe choices are rejected during feat selection.; A critical hit that deals no damage does not grant the temporary retaliation bonus.
- AC: Failure Modes-1, Failure Modes-2
