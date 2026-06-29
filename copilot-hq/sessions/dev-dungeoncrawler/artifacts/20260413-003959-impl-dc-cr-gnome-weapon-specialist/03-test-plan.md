# Test Plan: dc-cr-gnome-weapon-specialist

## Coverage summary
- AC items: 8 (4 happy path, 2 edge cases, 2 failure modes)
- Test cases: 5 (TC-GWS-01-05)
- Suites: playwright (feat progression, encounter)
- Security: AC exemption granted (existing combat routes only)

---

## TC-GWS-01 — Prerequisite-gated availability
- Description: Feat appears only after Gnome Weapon Familiarity is present.
- Suite: playwright/feat-progression
- Expected: feat locked before prerequisite, selectable after prerequisite
- AC: Availability, Failure Modes-1

## TC-GWS-02 — Glaive critical specialization applied
- Description: Character crits with a glaive.
- Suite: playwright/encounter
- Expected: glaive critical specialization effect is applied
- AC: Critical specialization trigger-1

## TC-GWS-03 — Kukri or gnome weapon specialization applied
- Description: Character crits with kukri or another gnome-tagged weapon.
- Suite: playwright/encounter
- Expected: matching critical specialization effect is applied
- AC: Critical specialization trigger-2, Critical specialization trigger-3

## TC-GWS-04 — Non-gnome weapon does not trigger
- Description: Character crits with an unrelated weapon.
- Suite: playwright/encounter
- Expected: no bonus specialization from this feat
- AC: Edge Cases-1

## TC-GWS-05 — Normal hit does not trigger
- Description: Character hits but does not critically hit.
- Suite: playwright/encounter
- Expected: no feat specialization bonus is applied
- AC: Edge Cases-2, Failure Modes-2
