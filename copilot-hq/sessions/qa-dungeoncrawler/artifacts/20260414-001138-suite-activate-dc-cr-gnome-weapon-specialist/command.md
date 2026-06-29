- Status: done
- Completed: 2026-04-14T00:31:01Z

# Suite Activation: dc-cr-gnome-weapon-specialist

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
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-gnome-weapon-specialist"`**  
   This links the test to the living requirements doc at `features/dc-cr-gnome-weapon-specialist/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-gnome-weapon-specialist-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-gnome-weapon-specialist",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-gnome-weapon-specialist"`**  
   Example:
   ```json
   {
     "id": "dc-cr-gnome-weapon-specialist-<route-slug>",
     "feature_id": "dc-cr-gnome-weapon-specialist",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-gnome-weapon-specialist",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-gnome-weapon-specialist

## Coverage summary
- AC items: 8 (4 happy path, 2 edge cases, 2 failure modes)
- Test cases: 5 (TC-GWS-01-05)
- Suites: playwright (feat progression, encounter)
- Security: AC exemption granted (existing combat routes only)

---

## TC-GWS-01 — Prerequisite-gated availability
- Description: Feat appears only after Gnome Weapon Familiarity is present.
- Suite: playwright/feat-progression
- Expected: feat locked before prerequisite, selectable after prerequisite
- AC: Availability, Failure Modes-1

## TC-GWS-02 — Glaive critical specialization applied
- Description: Character crits with a glaive.
- Suite: playwright/encounter
- Expected: glaive critical specialization effect is applied
- AC: Critical specialization trigger-1

## TC-GWS-03 — Kukri or gnome weapon specialization applied
- Description: Character crits with kukri or another gnome-tagged weapon.
- Suite: playwright/encounter
- Expected: matching critical specialization effect is applied
- AC: Critical specialization trigger-2, Critical specialization trigger-3

## TC-GWS-04 — Non-gnome weapon does not trigger
- Description: Character crits with an unrelated weapon.
- Suite: playwright/encounter
- Expected: no bonus specialization from this feat
- AC: Edge Cases-1

## TC-GWS-05 — Normal hit does not trigger
- Description: Character hits but does not critically hit.
- Suite: playwright/encounter
- Expected: no feat specialization bonus is applied
- AC: Edge Cases-2, Failure Modes-2

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-gnome-weapon-specialist

## Gap analysis reference
- DB sections: core/ch02 (Gnome ancestry feats)
- Depends on: dc-cr-gnome-ancestry, dc-cr-gnome-weapon-familiarity, dc-cr-ancestry-feat-schedule

---

## Happy Path

### Availability
- [ ] `[NEW]` Gnome Weapon Specialist is selectable as a Gnome feat 5 when Gnome Weapon Familiarity is present.

### Critical specialization trigger
- [ ] `[NEW]` Critical hits with glaive apply the weapon's critical specialization effect.
- [ ] `[NEW]` Critical hits with kukri apply the weapon's critical specialization effect.
- [ ] `[NEW]` Critical hits with other gnome-tagged weapons apply the weapon's critical specialization effect.

---

## Edge Cases
- [ ] `[NEW]` Non-gnome weapons do not trigger the feat.
- [ ] `[NEW]` The feat reuses the existing critical-specialization lookup rather than duplicating weapon-effect logic.

## Failure Modes
- [ ] `[TEST-ONLY]` Characters missing Gnome Weapon Familiarity cannot select or use the feat.
- [ ] `[TEST-ONLY]` Normal hits do not trigger the specialization effect.

## Security acceptance criteria
- Security AC exemption: combat hook only; no new route surface
