- Status: done
- Completed: 2026-04-14T00:18:59Z

# Suite Activation: dc-cr-gnome-obsession

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-14T00:11:38+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-gnome-obsession"`**  
   This links the test to the living requirements doc at `features/dc-cr-gnome-obsession/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-gnome-obsession-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-gnome-obsession",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-gnome-obsession"`**  
   Example:
   ```json
   {
     "id": "dc-cr-gnome-obsession-<route-slug>",
     "feature_id": "dc-cr-gnome-obsession",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-gnome-obsession",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-gnome-obsession

## Coverage summary
- AC items: 9 (5 happy path, 2 edge cases, 2 failure modes)
- Test cases: 5 (TC-GOBS-01-05)
- Suites: playwright (character creation, level-up, skill sheet)
- Security: AC exemption granted (existing feat/level-up routes only)

---

## TC-GOBS-01 — Feat availability and Lore selection
- Description: Gnome Obsession appears and allows a Lore choice.
- Suite: playwright/character-creation
- Expected: feat picker includes Gnome Obsession and Lore selector accepts Lore skills only
- AC: Availability, Lore selection and scaling-1, Failure Modes-1

## TC-GOBS-02 — Level 2 expert upgrade
- Description: Character levels from 1 to 2.
- Suite: playwright/level-up
- Expected: chosen Lore and background Lore (if any) upgrade to expert
- AC: Lore selection and scaling-2, Edge Cases-1

## TC-GOBS-03 — Level 7 master upgrade
- Description: Character reaches level 7.
- Suite: playwright/level-up
- Expected: tracked Lore skills upgrade to master
- AC: Lore selection and scaling-3

## TC-GOBS-04 — Level 15 legendary upgrade
- Description: Character reaches level 15.
- Suite: playwright/level-up
- Expected: tracked Lore skills upgrade to legendary
- AC: Lore selection and scaling-4

## TC-GOBS-05 — No off-schedule upgrades
- Description: Character levels to any non-milestone level.
- Suite: playwright/level-up
- Expected: no extra proficiency increase occurs from the feat
- AC: Failure Modes-2, Edge Cases-2

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-gnome-obsession

## Gap analysis reference
- DB sections: core/ch02 (Gnome ancestry feats)
- Depends on: dc-cr-gnome-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-character-leveling, dc-cr-skill-system

---

## Happy Path

### Availability
- [ ] `[NEW]` Gnome Obsession is selectable as a Gnome feat 1.

### Lore selection and scaling
- [ ] `[NEW]` On feat selection, the character chooses one Lore skill and becomes trained in it.
- [ ] `[NEW]` At level 2, the chosen Lore upgrades to expert, and the background Lore (if any) also upgrades to expert.
- [ ] `[NEW]` At level 7, the tracked Lore skills upgrade to master.
- [ ] `[NEW]` At level 15, the tracked Lore skills upgrade to legendary.

---

## Edge Cases
- [ ] `[NEW]` If the character has no background Lore, only the chosen Lore is auto-upgraded.
- [ ] `[NEW]` Manual proficiency edits do not break the milestone auto-upgrade path.

## Failure Modes
- [ ] `[TEST-ONLY]` Non-Lore skills cannot be chosen for the feat.
- [ ] `[TEST-ONLY]` The auto-upgrades do not fire at levels other than 2, 7, and 15.

## Security acceptance criteria
- Security AC exemption: skill-progression data only; no new route surface
