- Status: done
- Completed: 2026-04-12T05:13:34Z

# Suite Activation: dc-cr-gnome-heritage-chameleon

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-11T23:56:11+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-gnome-heritage-chameleon"`**  
   This links the test to the living requirements doc at `features/dc-cr-gnome-heritage-chameleon/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-gnome-heritage-chameleon-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-gnome-heritage-chameleon",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-gnome-heritage-chameleon"`**  
   Example:
   ```json
   {
     "id": "dc-cr-gnome-heritage-chameleon-<route-slug>",
     "feature_id": "dc-cr-gnome-heritage-chameleon",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-gnome-heritage-chameleon",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-gnome-heritage-chameleon

## Coverage summary
- AC items: 8 (5 happy path, 2 edge cases, 1 failure mode)
- Test cases: 6 (TC-CHM-01–06)
- Suites: playwright (character creation, encounter/stealth flows)
- Security: AC exemption granted (no new routes)

---

## TC-CHM-01 — Heritage selectable for Gnome
- Description: Chameleon Gnome appears in heritage options when Gnome is chosen
- Suite: playwright/character-creation
- Expected: heritage_options includes chameleon-gnome
- AC: Heritage Availability

## TC-CHM-02 — Stealth bonus in matching terrain
- Description: Character in terrain with matching coloration-tag receives +2 circumstance bonus to Stealth
- Suite: playwright/encounter
- Expected: stealth_roll.circumstance_bonus = 2 when terrain_tag matches character.coloration_tag
- AC: Passive Stealth Bonus-1, Passive Stealth Bonus-3

## TC-CHM-03 — Stealth bonus lost on terrain change
- Description: Bonus is removed when environment changes to non-matching terrain
- Suite: playwright/encounter
- Expected: stealth_roll.circumstance_bonus = 0 after terrain changes to non-matching type
- AC: Passive Stealth Bonus-2, Failure Modes-1

## TC-CHM-04 — 1-action color shift grants bonus
- Description: Spending 1 action enables the Stealth bonus (sets coloration to matching)
- Suite: playwright/encounter
- Expected: action color-shift sets character.coloration_tag to current terrain_tag; next Stealth check gets +2
- AC: Minor Color Shift

## TC-CHM-05 — Circumstance bonus does not stack
- Description: Multiple circumstance bonuses to Stealth — only highest applies
- Suite: playwright/encounter
- Expected: when two circumstance bonuses exist, character.stealth_circumstance_bonus = max(both)
- AC: Edge Case-2

## TC-CHM-06 — Dramatic shift takes 1 hour
- Description: Dramatic full-body coloration change is a downtime activity taking up to 1 hour
- Suite: playwright/downtime
- Expected: dramatic-color-shift action classified as downtime; duration = 60 min
- AC: Dramatic Color Shift

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-gnome-heritage-chameleon

## Gap analysis reference
- DB sections: core/ch02 (Gnome Heritages)
- Depends on: dc-cr-gnome-ancestry ✓, dc-cr-stealth (terrain-tag system)

---

## Happy Path

### Heritage Availability
- [ ] `[NEW]` Chameleon Gnome heritage is selectable when Gnome ancestry is chosen.

### Passive Stealth Bonus
- [ ] `[NEW]` When character is in a terrain whose color/pattern roughly matches their current coloration, +2 circumstance bonus applied to all Stealth checks.
- [ ] `[NEW]` The bonus is lost when the environment's coloration or pattern changes significantly.
- [ ] `[NEW]` The bonus is conditional: system applies it only when terrain-tag and character coloration-tag are compatible.

### Minor Color Shift (1 action)
- [ ] `[NEW]` Character can spend 1 action to make minor localized color shifts, enabling the Stealth bonus in matching terrain.

### Dramatic Color Shift
- [ ] `[NEW]` A dramatic full-body coloration change (to match different terrain) takes up to 1 hour (downtime activity).

---

## Edge Cases
- [ ] `[NEW]` Stealth bonus applies only in matching terrain — not generically in all environments.
- [ ] `[NEW]` Multiple circumstance bonuses to Stealth do not stack; only the highest applies.

## Failure Modes
- [ ] `[TEST-ONLY]` Bonus does not persist after terrain changes to non-matching environment.

## Security acceptance criteria
- Security AC exemption: game-mechanic heritage; no new routes or user-facing input
- Agent: qa-dungeoncrawler
- Status: pending
