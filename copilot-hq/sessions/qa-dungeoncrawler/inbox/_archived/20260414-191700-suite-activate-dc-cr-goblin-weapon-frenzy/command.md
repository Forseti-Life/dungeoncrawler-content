# Suite Activation: dc-cr-goblin-weapon-frenzy

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-14T19:17:00+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-goblin-weapon-frenzy"`**  
   This links the test to the living requirements doc at `features/dc-cr-goblin-weapon-frenzy/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-goblin-weapon-frenzy-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-goblin-weapon-frenzy",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-goblin-weapon-frenzy"`**  
   Example:
   ```json
   {
     "id": "dc-cr-goblin-weapon-frenzy-<route-slug>",
     "feature_id": "dc-cr-goblin-weapon-frenzy",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-goblin-weapon-frenzy",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-goblin-weapon-frenzy

## Coverage summary
- AC items: 7 (3 happy path, 2 edge cases, 2 failure modes)
- Test cases: 5 (TC-GWFZ-01-05)
- Suites: playwright (encounter, feat progression)
- Security: AC exemption granted (existing combat routes only)

---

## TC-GWFZ-01 — Prerequisite-gated feat availability
- Description: Goblin Weapon Frenzy appears only after Goblin Weapon Familiarity is present.
- Suite: playwright/feat-progression
- Expected: feat locked before prerequisite, selectable after prerequisite
- AC: Availability, Failure Modes-1

## TC-GWFZ-02 — Critical hit with goblin weapon applies specialization
- Description: Character crits with dogslicer or horsechopper.
- Suite: playwright/encounter
- Expected: matching critical specialization effect is applied
- AC: Critical specialization trigger-1, Critical specialization trigger-2

## TC-GWFZ-03 — Non-goblin weapon does not trigger
- Description: Character crits with a non-goblin weapon while having the feat.
- Suite: playwright/encounter
- Expected: no Goblin Weapon Frenzy specialization bonus is applied
- AC: Edge Cases-1

## TC-GWFZ-04 — Normal hit does not trigger
- Description: Character hits but does not critically hit.
- Suite: playwright/encounter
- Expected: no critical specialization effect from this feat
- AC: Failure Modes-2

## TC-GWFZ-05 — Existing specialization table reused
- Description: Trigger path routes through the standard crit-specialization resolver.
- Suite: playwright/encounter
- Expected: specialization effect matches the existing weapon lookup for the weapon type
- AC: Edge Cases-2

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-goblin-weapon-frenzy

## Gap analysis reference
- DB sections: core/ch02 (Goblin ancestry feats)
- Depends on: dc-cr-goblin-ancestry, dc-cr-goblin-weapon-familiarity, dc-cr-ancestry-feat-schedule

---

## Happy Path

### Availability
- [ ] `[NEW]` Goblin Weapon Frenzy is selectable as a Goblin feat 5 when Goblin Weapon Familiarity is already present.

### Critical specialization trigger
- [ ] `[NEW]` On a critical hit with a goblin weapon, the appropriate weapon critical specialization effect is applied.
- [ ] `[NEW]` The effect works for `dogslicer`, `horsechopper`, and other goblin-tagged weapons that the character is proficient with.

---

## Edge Cases
- [ ] `[NEW]` Critical hits with non-goblin weapons do not trigger Goblin Weapon Frenzy.
- [ ] `[NEW]` The feature piggybacks on the existing critical specialization lookup instead of duplicating weapon-effect logic.

## Failure Modes
- [ ] `[TEST-ONLY]` Characters missing Goblin Weapon Familiarity cannot select or use Goblin Weapon Frenzy.
- [ ] `[TEST-ONLY]` A normal hit does not trigger a specialization effect from this feat.

## Security acceptance criteria
- Security AC exemption: combat-resolution hook only; no new route surface
- Agent: qa-dungeoncrawler
- Status: pending
