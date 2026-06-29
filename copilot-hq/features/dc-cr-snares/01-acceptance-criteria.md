# Acceptance Criteria: dc-cr-snares

## Gap analysis reference
- DB sections: core/ch11/Snares (7 reqs) — already covered in dc-cr-magic-ch11 and flipped to in_progress.
  This feature focuses on snares as a distinct ranger/crafting subsystem.
- Depends on: dc-cr-equipment-system ✓, dc-cr-skill-system ✓, dc-cr-class-ranger

---

## Happy Path

### Crafting Snares
- [ ] `[EXTEND]` Snares require the Snare Crafting feat and a snare kit.
- [ ] `[EXTEND]` Snares occupy one 5-ft square and cannot be relocated after placement.
- [ ] `[EXTEND]` Quick crafting: 1 minute at full price; discounted version: downtime Craft activity.
- [ ] `[EXTEND]` Crafting produces a functional snare in place; no inventory item stored.

### Detection and Disabling
- [ ] `[EXTEND]` Detection DC = creator's Crafting DC; Disable DC = same (Thievery skill).
- [ ] `[EXTEND]` Expert+ Crafter snares: only found by actively-searching creatures (passive observers fail automatically).
- [ ] `[EXTEND]` Detection/disable minimum proficiency gates: Trained/Expert/Master based on creator's Crafting proficiency.
- [ ] `[EXTEND]` Creator disarms their own snare: 1 Interact action while adjacent (no check required).

### Snare Types (initial catalog)
- [ ] `[NEW]` Alarm Snare: triggers an audible alarm on trigger condition (loud noise in 300-ft radius).
- [ ] `[NEW]` Hampering Snare: terrain becomes difficult or greater difficult terrain for triggering creature; persists for limited time.
- [ ] `[NEW]` Marking Snare: applies a visual marker to the triggering creature (useful for tracking).
- [ ] `[NEW]` Striking Snare: deals physical damage to triggering creature (damage scales with snare level).

---

## Edge Cases
- [ ] `[EXTEND]` Snare detection while actively searching: normal Perception check vs Detection DC.
- [ ] `[EXTEND]` Snare placement in occupied square: blocked.

## Failure Modes
- [ ] `[TEST-ONLY]` Snare Crafting without Snare Crafting feat: blocked (not just penalized).
- [ ] `[TEST-ONLY]` Creator disarms own snare without Thievery check: 1 Interact action only.

## Security acceptance criteria
- Security AC exemption: game-mechanic snare crafting and placement logic; no new routes or user-facing input beyond existing exploration and encounter handlers
