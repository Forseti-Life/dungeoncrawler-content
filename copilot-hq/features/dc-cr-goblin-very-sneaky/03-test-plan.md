# Test Plan: dc-cr-goblin-very-sneaky

## Coverage summary
- AC items: 8 (3 happy path, 2 edge cases, 2 failure modes)
- Test cases: 5 (TC-GVS-01-05)
- Suites: playwright (encounter, stealth/exploration)
- Security: AC exemption granted (existing action routes only)

---

## TC-GVS-01 — Feat availability
- Description: Very Sneaky appears in the Goblin ancestry feat list.
- Suite: playwright/character-creation
- Expected: goblin feat picker includes `dc-cr-goblin-very-sneaky`
- AC: Availability

## TC-GVS-02 — Sneak gains +5 feet up to Speed
- Description: Sneak action gets an extra 5 feet without exceeding total Speed.
- Suite: playwright/encounter
- Expected: sneak_distance = min(base_sneak + 5, character.speed)
- AC: Sneak movement bonus, Edge Cases-1

## TC-GVS-03 — Cover at end of turn prevents Observed
- Description: Character ends the action exposed but ends the turn in cover after another successful Sneak.
- Suite: playwright/encounter
- Expected: visibility does not advance to Observed
- AC: End-of-turn visibility rule, Edge Cases-2

## TC-GVS-04 — Failed Sneak uses normal visibility resolution
- Description: Stealth failure with the feat still resolves as a normal failed Sneak.
- Suite: playwright/encounter
- Expected: default visibility outcome applied
- AC: Failure Modes-1

## TC-GVS-05 — Character without feat gets default behavior
- Description: Non-qualified character uses standard Sneak rules.
- Suite: playwright/encounter
- Expected: no +5 feet; visibility checked at end of action
- AC: Failure Modes-2
