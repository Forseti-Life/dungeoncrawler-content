- Status: done
- Completed: 2026-04-19T05:34:34Z

# Suite Activation: dc-gng-guns-gears

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
   **CRITICAL: tag every new entry with `"feature_id": "dc-gng-guns-gears"`**  
   This links the test to the living requirements doc at `features/dc-gng-guns-gears/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-gng-guns-gears-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-gng-guns-gears",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-gng-guns-gears"`**  
   Example:
   ```json
   {
     "id": "dc-gng-guns-gears-<route-slug>",
     "feature_id": "dc-gng-guns-gears",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-gng-guns-gears",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-gng-guns-gears

## Coverage summary
- AC items: 14 (Gunslinger, Inventor, firearm state, combination weapons, construct companion, access control)
- Test cases: 7 (TC-GNG-01-07)
- Suites: Playwright character/encounter flows + phpunit rules-state validation
- Security: character ownership enforced for class, firearm, and construct mutations

---

## TC-GNG-01 — Gunslinger class selection and Way persistence
- Description: Create or edit a character into Gunslinger and select a Way.
- Suite: `dc-gng-guns-gears-e2e`
- Expected: Gunslinger appears as a selectable class, valid Way choices persist, and Gunslinger-only actions/features appear for eligible characters.
- AC: Gunslinger Class 1-3

## TC-GNG-02 — Inventor class selection and Innovation persistence
- Description: Create or edit a character into Inventor and select Construct, Weapon, or Armor innovation.
- Suite: `dc-gng-guns-gears-e2e`
- Expected: Innovation selection validates server-side and Inventor-only actions/states are available on the owning character.
- AC: Inventor Class 1-3

## TC-GNG-03 — Reload and ammo state resolve server-side
- Description: Use firearms with reload 0 and reload 1+ in encounter flow.
- Suite: `dc-gng-guns-gears-e2e`
- Expected: reload counts, ammo state, and action consumption follow server-side rules; reload 0 does not consume unnecessary extra actions.
- AC: Firearms and Combination Weapons 1-2, Edge Cases 1

## TC-GNG-04 — Misfire and jam recovery are authoritative
- Description: Force a misfire or critical misfire path on a firearm, then clear the jam.
- Suite: `dc-gng-guns-gears-e2e`
- Expected: jammed state is computed server-side, contradictory client state is rejected, and recovery requires the documented action path.
- AC: Firearms and Combination Weapons 3, Access and Rules Integrity 2, Edge Cases 2, Failure Modes 3

## TC-GNG-05 — Combination weapon mode changes preserve item identity
- Description: Switch a combination weapon between ranged and melee modes.
- Suite: `dc-gng-guns-gears-e2e`
- Expected: the same owned item persists across both modes with consistent metadata and combat resolution.
- AC: Firearms and Combination Weapons 4, Edge Cases 3

## TC-GNG-06 — Construct Companion remains character-scoped
- Description: Create and use a Construct Companion from an Inventor character.
- Suite: `dc-gng-guns-gears-e2e`
- Expected: construct state remains tied to the owning character, uses companion/minion patterns correctly, and does not double-spend shared actions.
- AC: Construct Companion System 1-2, Edge Cases 4

## TC-GNG-07 — Ownership and enum validation reject invalid mutations
- Description: Attempt invalid Way/Innovation submissions and cross-character state mutations.
- Suite: `dc-gng-guns-gears-e2e`
- Expected: invalid enums return explicit validation errors and non-owners receive 403 on firearm, class-state, or construct mutations.
- AC: Access and Rules Integrity 1-2, Failure Modes 1-2

### Acceptance criteria (reference)

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
- Agent: qa-dungeoncrawler
- Status: pending
