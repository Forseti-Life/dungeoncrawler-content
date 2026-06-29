# Test Plan: dc-cr-gnome-weapon-expertise

## Coverage summary
- AC items: 8 (4 happy path, 2 edge cases, 2 failure modes)
- Test cases: 5 (TC-GWE-01-05)
- Suites: playwright (feat progression, level-up, proficiency sheet)
- Security: AC exemption granted (existing progression routes only)

---

## TC-GWE-01 — Prerequisite-gated feat availability
- Description: Feat appears only when Gnome Weapon Familiarity is present.
- Suite: playwright/feat-progression
- Expected: feat locked before prerequisite, selectable after prerequisite
- AC: Availability, Failure Modes-1

## TC-GWE-02 — Expert cascade applies to glaive and kukri
- Description: Class grants expert weapon proficiency.
- Suite: playwright/level-up
- Expected: glaive and kukri gain matching expert proficiency
- AC: Proficiency cascade-1, Proficiency cascade-2

## TC-GWE-03 — Trained gnome weapons receive cascade
- Description: Character is trained in a gnome weapon and later gains a higher class proficiency.
- Suite: playwright/level-up
- Expected: trained gnome weapon rank rises to the same class-granted rank
- AC: Proficiency cascade-3, Edge Cases-1

## TC-GWE-04 — Later class upgrades continue to cascade
- Description: Character later gains master or legendary proficiency from class features.
- Suite: playwright/level-up
- Expected: affected gnome weapons continue to match the new class-granted rank
- AC: Edge Cases-2

## TC-GWE-05 — Non-class change does not trigger
- Description: A non-class proficiency edit occurs.
- Suite: playwright/level-up
- Expected: no unwanted cascade event fires
- AC: Failure Modes-2
