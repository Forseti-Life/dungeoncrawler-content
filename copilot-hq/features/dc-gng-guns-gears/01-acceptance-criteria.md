# Acceptance Criteria: dc-gng-guns-gears

## Gap analysis reference
- DB sections: gng/ch01–ch05 (30 REQs)
- Depends on: dc-cr-equipment-ch06 ✓, dc-cr-encounter-rules ✓
- Track B: class-specific Gunslinger/Inventor behavior is mostly `[NEW]`; firearm and equipment integration extends existing combat/equipment systems as `[EXTEND]`

---

## Happy Path

### Gunslinger Class
- [ ] `[NEW]` Gunslinger is available as a selectable class with its documented class identity, core proficiencies, and class progression integrated into the existing class-selection flow.
- [ ] `[NEW]` Gunslinger supports Way selection using a validated server-side enum and persists the chosen Way on the character.
- [ ] `[NEW]` Gunslinger-specific class features such as firearm-focused expertise and reload-centric combat actions are available only to eligible Gunslinger characters.

### Inventor Class
- [ ] `[NEW]` Inventor is available as a selectable class with its documented class identity, progression, and feature unlock path integrated into the existing class system.
- [ ] `[NEW]` Inventor supports Innovation selection (Construct, Weapon, Armor) using a validated server-side enum.
- [ ] `[NEW]` Inventor-only actions and states, including Overdrive and unstable-action behavior, are tracked on the character and resolved by server-side game logic.

### Firearms and Combination Weapons
- [ ] `[EXTEND]` Weapon data supports firearm and combination-weapon entries using the shared equipment model instead of a parallel schema.
- [ ] `[EXTEND]` Firearms support reload values, ammunition state, and reload actions using the action economy already used by the encounter system.
- [ ] `[EXTEND]` Misfire and critical misfire outcomes are resolved server-side, including jammed-weapon state and the required recovery action.
- [ ] `[EXTEND]` Combination weapons can switch or resolve between ranged and melee modes without losing weapon identity, ownership, or rune/stat metadata.

### Construct Companion System
- [ ] `[EXTEND]` Construct Companion extends the companion framework with construct-specific rules, advancement, and traits while remaining character-scoped.
- [ ] `[EXTEND]` Construct Companion progression and state stay tied to the owning Inventor character and respect normal ownership and encounter boundaries.

### Access and Rules Integrity
- [ ] `[EXTEND]` Class selection, innovation selection, firearm state changes, and construct-companion mutations require character ownership (`_character_access: TRUE`).
- [ ] `[EXTEND]` Reload counts, jammed state, and unstable-action outcomes are computed by the server rather than trusted from client-submitted state.

---

## Edge Cases
- [ ] `[EXTEND]` Reload 0 weapons behave differently from reload 1+ weapons without consuming unnecessary actions.
- [ ] `[EXTEND]` A misfire on a weapon that is already jammed does not silently reset the jammed state or produce contradictory weapon status.
- [ ] `[EXTEND]` Characters switching between melee and ranged modes on a combination weapon preserve the same underlying item instance and attached metadata.
- [ ] `[EXTEND]` Construct Companion rules coexist cleanly with existing companion or minion action handling and do not double-spend shared actions.

## Failure Modes
- [ ] `[TEST-ONLY]` Invalid Way or Innovation enum values are rejected with explicit feedback.
- [ ] `[TEST-ONLY]` Non-owners receive a 403 when attempting to mutate firearm state, class-specific actions, or construct-companion data.
- [ ] `[TEST-ONLY]` Client-submitted reload or misfire state that conflicts with server-side rules is ignored or rejected rather than persisted.

## Security acceptance criteria
- Authentication: authenticated users only; class/weapon/construct mutations require `_character_access: TRUE`
- CSRF: all POST/PATCH class, weapon, and construct-companion routes require `_csrf_request_header_mode: TRUE`
- Input validation: Way and Innovation enums validate server-side; reload, jam, and unstable state are server-computed
- PII/logging: no PII logged; character id + weapon/construct id + action type only
