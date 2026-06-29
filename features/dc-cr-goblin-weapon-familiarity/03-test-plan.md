# Test Plan: dc-cr-goblin-weapon-familiarity

## Coverage summary
- AC items: 10 (6 happy path, 2 edge cases, 2 failure modes)
- Test cases: 6 (TC-GWF-01-06)
- Suites: playwright (character creation, inventory, feat progression)
- Security: AC exemption granted (existing character routes only)

---

## TC-GWF-01 — Goblin feat availability
- Description: Goblin Weapon Familiarity appears in the Goblin level-1 ancestry feat list.
- Suite: playwright/character-creation
- Expected: goblin feat picker includes `dc-cr-goblin-weapon-familiarity`
- AC: Availability

## TC-GWF-02 — Dogslicer and horsechopper proficiency granted
- Description: Selecting the feat grants trained proficiency with both signature goblin weapons.
- Suite: playwright/character-creation
- Expected: character.weapon_proficiencies includes `dogslicer=trained`, `horsechopper=trained`
- AC: Granted proficiencies-1, Granted proficiencies-2

## TC-GWF-03 — Uncommon goblin weapons unlocked
- Description: Uncommon goblin weapons become selectable after the feat is applied.
- Suite: playwright/inventory
- Expected: goblin-only uncommon weapons are visible/valid for the character
- AC: Weapon access and proficiency remap-1

## TC-GWF-04 — Proficiency remap applied
- Description: Goblin weapon proficiency tiers are remapped down by one step for proficiency checks.
- Suite: playwright/inventory
- Expected: martial goblin weapons resolve through simple proficiency; advanced goblin weapons resolve through martial proficiency
- AC: Weapon access and proficiency remap-2, Weapon access and proficiency remap-3, Edge Cases-1

## TC-GWF-05 — Non-goblin blocked from feat
- Description: Non-goblin character cannot select Goblin Weapon Familiarity.
- Suite: playwright/character-creation
- Expected: feat is hidden or rejected with prerequisite failure
- AC: Failure Modes-1

## TC-GWF-06 — Goblin Weapon Frenzy prerequisite opens
- Description: Goblin Weapon Familiarity satisfies the prerequisite gate for Goblin Weapon Frenzy.
- Suite: playwright/feat-progression
- Expected: Goblin Weapon Frenzy becomes eligible when other prerequisites are met
- AC: Failure Modes-2
