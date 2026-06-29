# Test Plan: dc-cr-focus-spells

## Coverage summary
- AC items: 9 (4 happy path, 3 edge cases, 2 failure modes)
- Test cases: 5 (TC-FCS-01-05)
- Suites: playwright (character build, spellcasting, rest/refocus)
- Security: Security AC exemption: spellcasting rules and character-state scope only; no new public routes expected beyond existing spellcasting and rest/action handlers.

---

## TC-FCS-01 — Feature availability and subsystem entry points
- Description: Classes and archetypes that grant focus spells also grant a focus-point pool and known focus-spell entries in character state.
- Suite: playwright/character-creation
- Expected: Classes and archetypes that grant focus spells also grant a focus-point pool and known focus-spell entries in character state.
- AC: Happy Path-1

## TC-FCS-02 — Primary subsystem rule resolution
- Description: Casting a focus spell consumes focus points instead of spell slots.
- Suite: playwright/encounter
- Expected: Casting a focus spell consumes focus points instead of spell slots.; Focus-point pools never exceed the rules cap of 3.
- AC: Happy Path-2, Happy Path-3

## TC-FCS-03 — State recovery, caps, or long-running flow handling
- Description: Focus-point pools never exceed the rules cap of 3.
- Suite: playwright/rest
- Expected: Focus-point pools never exceed the rules cap of 3.; A valid Refocus action after 10 minutes restores focus points according to the feature scope, and focus spells auto-heighten to the highest spell level the character can cast.
- AC: Happy Path-3, Happy Path-4

## TC-FCS-04 — Edge-case subsystem coverage
- Description: Characters with no focus pool never see focus-spell casting options.
- Suite: playwright/encounter
- Expected: Characters with no focus pool never see focus-spell casting options.; A character at 0 focus points cannot cast another focus spell until points are restored.; Multiple sources of focus spells share the same capped pool instead of creating separate point trackers.
- AC: Edge Cases-1, Edge Cases-2, Edge Cases-3

## TC-FCS-05 — Validation errors and wrong-surface rejection handling
- Description: Attempting to cast an unknown focus spell or Refocus when prerequisites are not met returns a validation error rather than silently failing.
- Suite: playwright/encounter
- Expected: Attempting to cast an unknown focus spell or Refocus when prerequisites are not met returns a validation error rather than silently failing.; Focus-spell casts do not consume standard spell slots or prepared-spell uses by mistake.
- AC: Failure Modes-1, Failure Modes-2
