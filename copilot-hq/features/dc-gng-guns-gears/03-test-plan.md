# Test Plan: dc-gng-guns-gears

## Coverage summary
- AC items: 14 (Gunslinger, Inventor, firearm state, combination weapons, construct companion, access control)
- Test cases: 7 (TC-GNG-01-07)
- Suites: Playwright character/encounter flows + phpunit rules-state validation
- Security: character ownership enforced for class, firearm, and construct mutations

---

## TC-GNG-01 — Gunslinger class selection and Way persistence
- Description: Create or edit a character into Gunslinger and select a Way.
- Suite: `dc-gng-guns-gears-e2e`
- Expected: Gunslinger appears as a selectable class, valid Way choices persist, and Gunslinger-only actions/features appear for eligible characters.
- AC: Gunslinger Class 1-3

## TC-GNG-02 — Inventor class selection and Innovation persistence
- Description: Create or edit a character into Inventor and select Construct, Weapon, or Armor innovation.
- Suite: `dc-gng-guns-gears-e2e`
- Expected: Innovation selection validates server-side and Inventor-only actions/states are available on the owning character.
- AC: Inventor Class 1-3

## TC-GNG-03 — Reload and ammo state resolve server-side
- Description: Use firearms with reload 0 and reload 1+ in encounter flow.
- Suite: `dc-gng-guns-gears-e2e`
- Expected: reload counts, ammo state, and action consumption follow server-side rules; reload 0 does not consume unnecessary extra actions.
- AC: Firearms and Combination Weapons 1-2, Edge Cases 1

## TC-GNG-04 — Misfire and jam recovery are authoritative
- Description: Force a misfire or critical misfire path on a firearm, then clear the jam.
- Suite: `dc-gng-guns-gears-e2e`
- Expected: jammed state is computed server-side, contradictory client state is rejected, and recovery requires the documented action path.
- AC: Firearms and Combination Weapons 3, Access and Rules Integrity 2, Edge Cases 2, Failure Modes 3

## TC-GNG-05 — Combination weapon mode changes preserve item identity
- Description: Switch a combination weapon between ranged and melee modes.
- Suite: `dc-gng-guns-gears-e2e`
- Expected: the same owned item persists across both modes with consistent metadata and combat resolution.
- AC: Firearms and Combination Weapons 4, Edge Cases 3

## TC-GNG-06 — Construct Companion remains character-scoped
- Description: Create and use a Construct Companion from an Inventor character.
- Suite: `dc-gng-guns-gears-e2e`
- Expected: construct state remains tied to the owning character, uses companion/minion patterns correctly, and does not double-spend shared actions.
- AC: Construct Companion System 1-2, Edge Cases 4

## TC-GNG-07 — Ownership and enum validation reject invalid mutations
- Description: Attempt invalid Way/Innovation submissions and cross-character state mutations.
- Suite: `dc-gng-guns-gears-e2e`
- Expected: invalid enums return explicit validation errors and non-owners receive 403 on firearm, class-state, or construct mutations.
- AC: Access and Rules Integrity 1-2, Failure Modes 1-2
