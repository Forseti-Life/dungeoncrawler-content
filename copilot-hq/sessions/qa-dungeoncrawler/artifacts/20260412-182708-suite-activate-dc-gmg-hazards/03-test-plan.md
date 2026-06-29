# Test Plan: dc-gmg-hazards

## Coverage summary
- AC items: ~22 (hazard stat blocks, simple/complex/haunt types, disabled/destroyed/reset states, XP, NPC gallery integration)
- Test cases: 10 (TC-HAZ-01–10)
- Suites: playwright (encounter, character creation)
- Security: AC exemption granted (no new routes)

---

## TC-HAZ-01 — Hazard stat block completeness
- Description: Every hazard has all required fields: Stealth DC, Disable DC, AC, Saves, HP, Hardness, Immunities, Weaknesses, Resistances, Actions
- Suite: playwright/encounter
- Expected: hazard detail view shows all fields; no null/missing required stat block fields
- AC: Hazard-1

## TC-HAZ-02 — Hazard detection: Stealth DC vs. Perception check
- Description: Hazard Stealth DC used for initial detection; Perception check vs. Stealth DC determines awareness
- Suite: playwright/encounter
- Expected: entering area → auto Perception check vs. hazard.stealth_dc; success → hazard revealed; fail → hazard hidden
- AC: Hazard-2

## TC-HAZ-03 — Hazard disarming: Disable DC via applicable skill
- Description: Disable check uses applicable skill (Thievery, Arcana, etc.) vs. Disable DC
- Suite: playwright/encounter
- Expected: disable action shows correct skill check; DC sourced from hazard.disable_dc; varied by hazard type
- AC: Hazard-3

## TC-HAZ-04 — Simple hazards: resolve in one action, no initiative
- Description: Simple hazards resolve on trigger (one action); no initiative entry required
- Suite: playwright/encounter
- Expected: simple hazard trigger → immediate resolution; no initiative tracker entry
- AC: Hazard-4

## TC-HAZ-05 — Complex hazards: join initiative when triggered
- Description: Complex hazards enter initiative when triggered; take own turns with defined actions
- Suite: playwright/encounter
- Expected: complex hazard trigger → added to initiative order; takes turns with its action block
- AC: Hazard-5

## TC-HAZ-06 — Haunt hazards: deactivated (not destroyed) after disable; re-activates
- Description: Haunt not destroyed until underlying supernatural condition resolved; disable = temporary (deactivated state only); re-activates on next trigger
- Suite: playwright/encounter
- Expected: haunt.disabled → state = deactivated (not destroyed); next trigger re-activates; destruction requires separate condition resolution
- AC: Hazard-7

## TC-HAZ-07 — Disabled vs. Destroyed state distinction; reset behavior
- Description: Disabled = inactive until reset; Destroyed = removed permanently; some hazards auto-reset after time interval; others require manual reset
- Suite: playwright/encounter
- Expected: disabled hazard shows "disabled" state; timed reset restores after interval; manual-reset hazard stays disabled until GM action; destroyed = removed from encounter
- AC: Hazard-8–9

## TC-HAZ-08 — Hazard XP award on disable or destroy
- Description: Hazards award XP when disabled or destroyed; same trigger framework as creature death
- Suite: playwright/encounter
- Expected: hazard.disabled or hazard.destroyed → XP awarded = hazard.xp_value; XP event fires once
- AC: Hazard-10

## TC-HAZ-09 — Hazard damage through existing pipeline (typed, resistances, immunities)
- Description: Hazard damage applies through standard damage pipeline; typed damage respected; resistances/immunities applied
- Suite: playwright/encounter
- Expected: fire trap damage checks fire resistance; hazard damage type stored in stat block; pipeline identical to creature attacks
- AC: Integration-4

## TC-HAZ-10 — APG hazards loaded alongside GMG hazards in catalog
- Description: APG-sourced hazards (Engulfing Snare, etc.) appear in hazard catalog alongside GMG hazards
- Suite: playwright/encounter
- Expected: hazard selector shows entries from both APG and GMG sources; filterble by source
- AC: Hazard-11
