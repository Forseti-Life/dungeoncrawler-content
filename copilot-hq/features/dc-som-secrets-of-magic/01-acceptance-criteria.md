# Acceptance Criteria: dc-som-secrets-of-magic

## Gap analysis reference
- DB sections: som/ch01–ch05 (30 REQs)
- Depends on: dc-cr-spellcasting ✓, dc-cr-class-wizard ✓, dc-cr-class-sorcerer ✓
- Track B: Magus and Summoner class systems are mostly `[NEW]`; spellcasting/tradition integrations extend existing spell infrastructure as `[EXTEND]`

---

## Happy Path

### Magus Class
- [ ] `[NEW]` Magus is available as a selectable class with its documented class identity, level progression, and class-specific feature unlocks integrated into the existing class system.
- [ ] `[NEW]` Magus supports Hybrid Study selection using a validated server-side enum and persists the chosen study on the character.
- [ ] `[NEW]` Arcane Cascade stance and other Magus-only stateful features are tracked on the character and enforced by server-side encounter logic.

### Spellstrike and Recharge Flow
- [ ] `[NEW]` Spellstrike is implemented as a combined class action that binds a valid spell to a strike, resolves the strike, and delivers spell effects according to hit/miss outcome.
- [ ] `[NEW]` Spellstrike state tracks whether the character is charged or uncharged and requires the documented recharge path before the next Spellstrike.
- [ ] `[EXTEND]` Spellstrike integrates with the existing spell-slot, spell-attack, and action-economy systems instead of introducing a separate casting subsystem.

### Summoner and Eidolon
- [ ] `[NEW]` Summoner is available as a selectable class with its documented class identity, progression, and class-specific feature unlocks integrated into the existing class system.
- [ ] `[NEW]` Summoner supports Eidolon selection using a validated server-side enum and creates a character-scoped Eidolon entity tied to that summoner.
- [ ] `[NEW]` Eidolon and summoner share HP and action-economy state according to the class rules, including the Act Together interaction.
- [ ] `[EXTEND]` Eidolon persistence, encounter participation, and ownership checks use existing companion/minion patterns where applicable without exposing cross-character access.

### Expanded Magic Systems
- [ ] `[EXTEND]` New spells, focus spells, and traditions introduced by Secrets of Magic are represented through the shared spell data model and spell-list infrastructure.
- [ ] `[EXTEND]` Cathartic, rune, soul, or similarly expanded magic classifications can be stored and queried without breaking existing spell-list behavior for core classes.

### Access and Rules Integrity
- [ ] `[EXTEND]` Class selection, Hybrid Study selection, Eidolon mutation, and class-specific state transitions require character ownership (`_character_access: TRUE`).
- [ ] `[EXTEND]` Spellstrike charged/uncharged state, Eidolon linkage, and shared-action resolution are computed server-side rather than accepted from client assertions.

---

## Edge Cases
- [ ] `[NEW]` A Magus cannot queue another Spellstrike while still uncharged from the prior use.
- [ ] `[EXTEND]` A missed strike still resolves Spellstrike state transitions correctly without duplicating spell effects.
- [ ] `[EXTEND]` An Eidolon cannot become detached from its owning Summoner or be reused by another character.
- [ ] `[EXTEND]` Shared HP and shared-action accounting remain consistent when the Summoner or Eidolon is incapacitated, dismissed, or absent from the current encounter.

## Failure Modes
- [ ] `[TEST-ONLY]` Invalid Hybrid Study or Eidolon enum values are rejected with explicit feedback.
- [ ] `[TEST-ONLY]` Non-owners receive a 403 when attempting to mutate Eidolon state or class-specific combat state.
- [ ] `[TEST-ONLY]` Client-submitted Spellstrike or shared-action state that conflicts with server rules is ignored or rejected.

## Security acceptance criteria
- Authentication: authenticated users only; Eidolon and class-state mutations require `_character_access: TRUE`
- CSRF: all POST/PATCH class, spell, and Eidolon routes require `_csrf_request_header_mode: TRUE`
- Input validation: Hybrid Study and Eidolon enums validate server-side; Spellstrike and shared-action state are server-computed
- PII/logging: no PII logged; character id + eidolon id + action type only
