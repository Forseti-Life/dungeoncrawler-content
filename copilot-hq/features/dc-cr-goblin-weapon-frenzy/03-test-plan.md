# Test Plan: dc-cr-goblin-weapon-frenzy

## Coverage summary
- AC items: 7 (3 happy path, 2 edge cases, 2 failure modes)
- Test cases: 5 (TC-GWFZ-01-05)
- Suites: playwright (encounter, feat progression)
- Security: AC exemption granted (existing combat routes only)

---

## TC-GWFZ-01 — Prerequisite-gated feat availability
- Description: Goblin Weapon Frenzy appears only after Goblin Weapon Familiarity is present.
- Suite: playwright/feat-progression
- Expected: feat locked before prerequisite, selectable after prerequisite
- AC: Availability, Failure Modes-1

## TC-GWFZ-02 — Critical hit with goblin weapon applies specialization
- Description: Character crits with dogslicer or horsechopper.
- Suite: playwright/encounter
- Expected: matching critical specialization effect is applied
- AC: Critical specialization trigger-1, Critical specialization trigger-2

## TC-GWFZ-03 — Non-goblin weapon does not trigger
- Description: Character crits with a non-goblin weapon while having the feat.
- Suite: playwright/encounter
- Expected: no Goblin Weapon Frenzy specialization bonus is applied
- AC: Edge Cases-1

## TC-GWFZ-04 — Normal hit does not trigger
- Description: Character hits but does not critically hit.
- Suite: playwright/encounter
- Expected: no critical specialization effect from this feat
- AC: Failure Modes-2

## TC-GWFZ-05 — Existing specialization table reused
- Description: Trigger path routes through the standard crit-specialization resolver.
- Suite: playwright/encounter
- Expected: specialization effect matches the existing weapon lookup for the weapon type
- AC: Edge Cases-2
