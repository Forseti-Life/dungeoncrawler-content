# Test Plan: dc-cr-dwarven-weapon-expertise

## Coverage summary
- AC items: 9 (4 happy path, 3 edge cases, 2 failure modes)
- Test cases: 5 (TC-DWE-01-05)
- Suites: playwright (feat progression, weapon proficiency, rebuild)
- Security: Security AC exemption: ancestry-feat and proficiency-calculation scope only; no new routes or input surfaces beyond existing feat assignment and character build handlers.

---

## TC-DWE-01 — Feat availability and prerequisite gating
- Description: The feat exists in the dwarf ancestry-feat catalog at level 13 with Dwarven Weapon Familiarity as a prerequisite.
- Suite: playwright/feat-progression
- Expected: The feat exists in the dwarf ancestry-feat catalog at level 13 with Dwarven Weapon Familiarity as a prerequisite.
- AC: Happy Path-1

## TC-DWE-02 — Primary granted benefit application
- Description: When the character gains a class feature that grants expert or higher weapon proficiency, that rank is copied to battle axes, picks, warhammers, and any trained dwarven weapons.
- Suite: playwright/inventory
- Expected: When the character gains a class feature that grants expert or higher weapon proficiency, that rank is copied to battle axes, picks, warhammers, and any trained dwarven weapons.; The upgrade uses the character's current trained dwarven-weapon set rather than granting expertise to unrelated weapon families.
- AC: Happy Path-2, Happy Path-3

## TC-DWE-03 — Recalculation, retraining, and later progression behavior
- Description: The upgrade uses the character's current trained dwarven-weapon set rather than granting expertise to unrelated weapon families.
- Suite: playwright/feat-progression
- Expected: The upgrade uses the character's current trained dwarven-weapon set rather than granting expertise to unrelated weapon families.; Rebuilds or later class-proficiency upgrades recalculate the dwarven-weapon expertise bonus correctly.
- AC: Happy Path-3, Happy Path-4

## TC-DWE-04 — Edge-case rules interaction coverage
- Description: Characters without the prerequisite feat cannot select Dwarven Weapon Expertise.
- Suite: playwright/feat-progression
- Expected: Characters without the prerequisite feat cannot select Dwarven Weapon Expertise.; If a weapon already has an equal or higher proficiency rank from another source, the feat does not downgrade or duplicate that rank.; New dwarven weapons learned later inherit the propagated proficiency if they satisfy the trained-weapon requirement.
- AC: Edge Cases-1, Edge Cases-2, Edge Cases-3

## TC-DWE-05 — Validation errors and malformed-data handling
- Description: Selecting the feat below level 13 or on a non-dwarf build fails validation.
- Suite: playwright/inventory
- Expected: Selecting the feat below level 13 or on a non-dwarf build fails validation.; Missing dwarven-weapon tags or malformed proficiency mappings do not crash the character sheet; they surface a validation defect instead.
- AC: Failure Modes-1, Failure Modes-2
