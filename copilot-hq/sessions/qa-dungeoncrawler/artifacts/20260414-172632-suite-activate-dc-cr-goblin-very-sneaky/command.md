- Status: done
- Completed: 2026-04-14T17:40:47Z

# Suite Activation: dc-cr-goblin-very-sneaky

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-14T17:26:32+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-goblin-very-sneaky"`**  
   This links the test to the living requirements doc at `features/dc-cr-goblin-very-sneaky/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-goblin-very-sneaky-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-goblin-very-sneaky",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-goblin-very-sneaky"`**  
   Example:
   ```json
   {
     "id": "dc-cr-goblin-very-sneaky-<route-slug>",
     "feature_id": "dc-cr-goblin-very-sneaky",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-goblin-very-sneaky",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-goblin-very-sneaky

## Coverage summary
- AC items: 8 (3 happy path, 2 edge cases, 2 failure modes)
- Test cases: 5 (TC-GVS-01-05)
- Suites: playwright (encounter, stealth/exploration)
- Security: AC exemption granted (existing action routes only)

---

## TC-GVS-01 — Feat availability
- Description: Very Sneaky appears in the Goblin ancestry feat list.
- Suite: playwright/character-creation
- Expected: goblin feat picker includes `dc-cr-goblin-very-sneaky`
- AC: Availability

## TC-GVS-02 — Sneak gains +5 feet up to Speed
- Description: Sneak action gets an extra 5 feet without exceeding total Speed.
- Suite: playwright/encounter
- Expected: sneak_distance = min(base_sneak + 5, character.speed)
- AC: Sneak movement bonus, Edge Cases-1

## TC-GVS-03 — Cover at end of turn prevents Observed
- Description: Character ends the action exposed but ends the turn in cover after another successful Sneak.
- Suite: playwright/encounter
- Expected: visibility does not advance to Observed
- AC: End-of-turn visibility rule, Edge Cases-2

## TC-GVS-04 — Failed Sneak uses normal visibility resolution
- Description: Stealth failure with the feat still resolves as a normal failed Sneak.
- Suite: playwright/encounter
- Expected: default visibility outcome applied
- AC: Failure Modes-1

## TC-GVS-05 — Character without feat gets default behavior
- Description: Non-qualified character uses standard Sneak rules.
- Suite: playwright/encounter
- Expected: no +5 feet; visibility checked at end of action
- AC: Failure Modes-2

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-goblin-very-sneaky

## Gap analysis reference
- DB sections: core/ch02 (Goblin ancestry feats)
- Depends on: dc-cr-goblin-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-skills-stealth-hide-sneak

---

## Happy Path

### Availability
- [ ] `[NEW]` Very Sneaky is selectable as a Goblin ancestry feat at level 1.

### Sneak movement bonus
- [ ] `[NEW]` A character with Very Sneaky can move 5 feet farther when using Sneak, up to their Speed.

### End-of-turn visibility rule
- [ ] `[NEW]` If the character continues using Sneak actions, succeeds at the Stealth check, and ends their turn with cover/greater cover/concealment, they do not become Observed even if they lacked it at the end of the Sneak action.

---

## Edge Cases
- [ ] `[NEW]` The bonus never allows movement beyond the character's Speed cap for the Sneak action.
- [ ] `[NEW]` The delayed visibility protection applies only if the character still ends the turn with cover or concealment.

## Failure Modes
- [ ] `[TEST-ONLY]` Failing the Sneak check still resolves normal visibility rules.
- [ ] `[TEST-ONLY]` Characters without the feat use the default Sneak distance and visibility timing.

## Security acceptance criteria
- Security AC exemption: action-resolution adjustment only; no new routes or data entry surface
