- Status: done
- Completed: 2026-04-12T16:43:48Z

# Suite Activation: dc-cr-gnome-heritage-umbral

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-12T13:56:28+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-gnome-heritage-umbral"`**  
   This links the test to the living requirements doc at `features/dc-cr-gnome-heritage-umbral/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-gnome-heritage-umbral-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-gnome-heritage-umbral",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-gnome-heritage-umbral"`**  
   Example:
   ```json
   {
     "id": "dc-cr-gnome-heritage-umbral-<route-slug>",
     "feature_id": "dc-cr-gnome-heritage-umbral",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-gnome-heritage-umbral",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-gnome-heritage-umbral

## Coverage summary
- AC items: 5 (3 happy path, 1 edge case, 1 failure mode)
- Test cases: 4 (TC-UMB-01–04)
- Suites: playwright (character creation, encounter lighting flows)
- Security: AC exemption granted (no new routes)

---

## TC-UMB-01 — Heritage selectable for Gnome
- Description: Umbral Gnome appears in heritage options when Gnome is chosen
- Suite: playwright/character-creation
- Expected: heritage_options includes umbral-gnome
- AC: Heritage Availability

## TC-UMB-02 — Darkvision sense granted
- Description: Umbral Gnome character has darkvision in sense list
- Suite: playwright/character-creation
- Expected: character.senses includes {type: darkvision}; uses shared darkvision sense type
- AC: Darkvision-1, Darkvision-2

## TC-UMB-03 — Darkvision in complete darkness
- Description: Character can see normally in completely dark environments (no light source)
- Suite: playwright/encounter
- Expected: when lighting = complete_darkness, Umbral Gnome has no vision penalty; standard sight range applies
- AC: Failure Modes-1

## TC-UMB-04 — No duplicate sense entry from stacking
- Description: If darkvision already present from another source, no duplicate entry added
- Suite: playwright/character-creation
- Expected: character.senses.count(type: darkvision) = 1 regardless of how many darkvision sources apply
- AC: Edge Case-1

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-gnome-heritage-umbral

## Gap analysis reference
- DB sections: core/ch02 (Gnome Heritages)
- Depends on: dc-cr-gnome-ancestry ✓, dc-cr-darkvision (shared sense type)

---

## Happy Path

### Darkvision
- [ ] `[NEW]` Umbral Gnome has darkvision — can see in complete darkness with no penalty.
- [ ] `[NEW]` Uses the shared darkvision sense type defined by dc-cr-darkvision; no new sense logic required.
- [ ] `[NEW]` Darkvision replaces (or supersedes) Low-Light Vision — gnome already has Low-Light Vision from ancestry, but darkvision is strictly superior.

### Heritage Availability
- [ ] `[NEW]` Umbral Gnome heritage is selectable when Gnome ancestry is chosen.

---

## Edge Cases
- [ ] `[NEW]` If darkvision is already granted by another source (feat, item), the character does not gain a duplicate sense entry.

## Failure Modes
- [ ] `[TEST-ONLY]` Darkvision grants vision in complete darkness — not just dim light (which is Low-Light Vision only).

## Security acceptance criteria
- Security AC exemption: game-mechanic sense; no new routes or user-facing input
- Agent: qa-dungeoncrawler
- Status: pending
