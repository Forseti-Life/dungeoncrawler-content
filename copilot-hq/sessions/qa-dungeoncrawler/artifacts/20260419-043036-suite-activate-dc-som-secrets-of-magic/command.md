- Status: done
- Completed: 2026-04-19T05:37:48Z

# Suite Activation: dc-som-secrets-of-magic

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-19T04:30:36+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-som-secrets-of-magic"`**  
   This links the test to the living requirements doc at `features/dc-som-secrets-of-magic/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-som-secrets-of-magic-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-som-secrets-of-magic",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-som-secrets-of-magic"`**  
   Example:
   ```json
   {
     "id": "dc-som-secrets-of-magic-<route-slug>",
     "feature_id": "dc-som-secrets-of-magic",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-som-secrets-of-magic",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-som-secrets-of-magic

## Coverage summary
- AC items: 15 (Magus, Spellstrike, Summoner, Eidolon, expanded spell systems, ownership and server authority)
- Test cases: 7 (TC-SOM-01-07)
- Suites: Playwright character/encounter flows + phpunit class/spell-state validation
- Security: character ownership enforced for class state, Spellstrike state, and Eidolon mutations

---

## TC-SOM-01 — Magus class selection and Hybrid Study persistence
- Description: Create or edit a character into Magus and select a Hybrid Study.
- Suite: `dc-som-secrets-of-magic-e2e`
- Expected: Magus is selectable, Hybrid Study validates server-side, and Magus-specific stateful features become available to the owning character.
- AC: Magus Class 1-3

## TC-SOM-02 — Spellstrike charge, strike, and recharge flow
- Description: Execute Spellstrike, observe hit/miss behavior, then attempt a second Spellstrike before and after recharge.
- Suite: `dc-som-secrets-of-magic-e2e`
- Expected: Spellstrike binds a valid spell to a strike, resolves hit/miss rules correctly, blocks reuse while uncharged, and reopens only after the documented recharge path.
- AC: Spellstrike and Recharge Flow 1-3, Edge Cases 1-2

## TC-SOM-03 — Arcane Cascade state is character-scoped
- Description: Enter Arcane Cascade after eligible Magus actions.
- Suite: `dc-som-secrets-of-magic-e2e`
- Expected: Arcane Cascade is tracked on the owning character and enforced by server-side encounter logic.
- AC: Magus Class 3

## TC-SOM-04 — Summoner creates a valid Eidolon
- Description: Create a Summoner and select an Eidolon type.
- Suite: `dc-som-secrets-of-magic-e2e`
- Expected: the Eidolon is created as a character-scoped entity tied only to the owning Summoner and valid Eidolon enums persist correctly.
- AC: Summoner and Eidolon 1-2, Failure Modes 1

## TC-SOM-05 — Summoner and Eidolon share HP and actions correctly
- Description: Use Summoner and Eidolon in encounter flow, including Act Together and incapacitation/dismissal edge cases.
- Suite: `dc-som-secrets-of-magic-e2e`
- Expected: HP and shared action state stay synchronized, Eidolon cannot detach to another character, and encounter state remains coherent during absence/incapacitation cases.
- AC: Summoner and Eidolon 3-4, Edge Cases 3-4

## TC-SOM-06 — Expanded spell-system metadata integrates with the shared spell model
- Description: Load Secrets of Magic spell/tradition data into the shared spell infrastructure.
- Suite: `dc-som-secrets-of-magic-e2e`
- Expected: new traditions/classifications are stored and queried through the shared spell model without breaking existing spell-list behavior.
- AC: Expanded Magic Systems 1-2

## TC-SOM-07 — Ownership and client-state tampering are rejected
- Description: Attempt invalid Hybrid Study/Eidolon submissions and client-side Spellstrike/shared-action tampering.
- Suite: `dc-som-secrets-of-magic-e2e`
- Expected: invalid enums produce explicit validation errors, non-owners receive 403, and conflicting client-submitted combat state is ignored or rejected.
- AC: Access and Rules Integrity 1-2, Failure Modes 2-3

### Acceptance criteria (reference)

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
- Agent: qa-dungeoncrawler
- Status: pending
