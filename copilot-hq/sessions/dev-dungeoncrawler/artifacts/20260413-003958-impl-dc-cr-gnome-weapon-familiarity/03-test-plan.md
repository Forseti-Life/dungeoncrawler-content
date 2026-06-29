# Test Plan: dc-cr-gnome-weapon-familiarity

## Coverage summary
- AC items: 8 (5 happy path, 2 edge cases, 2 failure modes)
- Test cases: 5 (TC-GWFM-01-05)
- Suites: playwright (character creation, inventory, feat progression)
- Security: AC exemption granted (existing feat/inventory routes only)

---

## TC-GWFM-01 — Feat availability
- Description: Gnome Weapon Familiarity appears in the Gnome feat list.
- Suite: playwright/character-creation
- Expected: feat picker includes `dc-cr-gnome-weapon-familiarity`
- AC: Availability

## TC-GWFM-02 — Glaive and kukri proficiency granted
- Description: Selecting the feat grants trained proficiency with both named weapons.
- Suite: playwright/character-creation
- Expected: weapon proficiencies include glaive and kukri at trained
- AC: Granted proficiencies-1, Granted proficiencies-2

## TC-GWFM-03 — Uncommon gnome weapons unlocked
- Description: Character gains access to uncommon gnome weapons.
- Suite: playwright/inventory
- Expected: uncommon gnome weapons become visible/valid for the character
- AC: Granted proficiencies-3

## TC-GWFM-04 — Martial gnome weapons remapped
- Description: Proficiency resolver treats martial gnome weapons as simple.
- Suite: playwright/inventory
- Expected: proficiency math uses simple-weapon proficiency tier for martial gnome weapons
- AC: Proficiency remap, Edge Cases-1

## TC-GWFM-05 — Downstream feat prerequisite opened
- Description: Gnome Weapon Specialist/Expertise unlock only after this feat is present.
- Suite: playwright/feat-progression
- Expected: downstream feat gates respect the prerequisite
- AC: Failure Modes-2, Failure Modes-1
