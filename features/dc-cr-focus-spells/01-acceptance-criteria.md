# Acceptance Criteria — dc-cr-focus-spells

- Feature: Focus Spells
- Release target: 20260412-dungeoncrawler-release-z
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-29

## Scope

Capture the focus-spell subsystem as a handoff-ready contract covering focus pools, focus-point consumption, Refocus recovery, and auto-heightening so QA can drive implementation across classes and archetypes.

## Dependency checkpoints

- Consolidated into: dc-cr-spells-ch07 (requirements covered in that feature's acceptance criteria)

## Happy Path

- [ ] `[NEW]` Classes and archetypes that grant focus spells also grant a focus-point pool and known focus-spell entries in character state.
- [ ] `[NEW]` Casting a focus spell consumes focus points instead of spell slots.
- [ ] `[NEW]` Focus-point pools never exceed the rules cap of 3.
- [ ] `[NEW]` A valid Refocus action after 10 minutes restores focus points according to the feature scope, and focus spells auto-heighten to the highest spell level the character can cast.

## Edge Cases

- [ ] `[NEW]` Characters with no focus pool never see focus-spell casting options.
- [ ] `[NEW]` A character at 0 focus points cannot cast another focus spell until points are restored.
- [ ] `[NEW]` Multiple sources of focus spells share the same capped pool instead of creating separate point trackers.

## Failure Modes

- [ ] `[NEW]` Attempting to cast an unknown focus spell or Refocus when prerequisites are not met returns a validation error rather than silently failing.
- [ ] `[NEW]` Focus-spell casts do not consume standard spell slots or prepared-spell uses by mistake.

## Security acceptance criteria

- Security AC exemption: spellcasting rules and character-state scope only; no new public routes expected beyond existing spellcasting and rest/action handlers.
