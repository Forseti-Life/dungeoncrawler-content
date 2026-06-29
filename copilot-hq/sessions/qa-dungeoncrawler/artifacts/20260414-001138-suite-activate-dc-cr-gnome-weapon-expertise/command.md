- Status: done
- Completed: 2026-04-14T00:22:34Z

# Suite Activation: dc-cr-gnome-weapon-expertise

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
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-gnome-weapon-expertise"`**  
   This links the test to the living requirements doc at `features/dc-cr-gnome-weapon-expertise/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-gnome-weapon-expertise-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-gnome-weapon-expertise",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-gnome-weapon-expertise"`**  
   Example:
   ```json
   {
     "id": "dc-cr-gnome-weapon-expertise-<route-slug>",
     "feature_id": "dc-cr-gnome-weapon-expertise",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-gnome-weapon-expertise",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-gnome-weapon-expertise

## Coverage summary
- AC items: 8 (4 happy path, 2 edge cases, 2 failure modes)
- Test cases: 5 (TC-GWE-01-05)
- Suites: playwright (feat progression, level-up, proficiency sheet)
- Security: AC exemption granted (existing progression routes only)

---

## TC-GWE-01 — Prerequisite-gated feat availability
- Description: Feat appears only when Gnome Weapon Familiarity is present.
- Suite: playwright/feat-progression
- Expected: feat locked before prerequisite, selectable after prerequisite
- AC: Availability, Failure Modes-1

## TC-GWE-02 — Expert cascade applies to glaive and kukri
- Description: Class grants expert weapon proficiency.
- Suite: playwright/level-up
- Expected: glaive and kukri gain matching expert proficiency
- AC: Proficiency cascade-1, Proficiency cascade-2

## TC-GWE-03 — Trained gnome weapons receive cascade
- Description: Character is trained in a gnome weapon and later gains a higher class proficiency.
- Suite: playwright/level-up
- Expected: trained gnome weapon rank rises to the same class-granted rank
- AC: Proficiency cascade-3, Edge Cases-1

## TC-GWE-04 — Later class upgrades continue to cascade
- Description: Character later gains master or legendary proficiency from class features.
- Suite: playwright/level-up
- Expected: affected gnome weapons continue to match the new class-granted rank
- AC: Edge Cases-2

## TC-GWE-05 — Non-class change does not trigger
- Description: A non-class proficiency edit occurs.
- Suite: playwright/level-up
- Expected: no unwanted cascade event fires
- AC: Failure Modes-2

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-gnome-weapon-expertise

## Gap analysis reference
- DB sections: core/ch02 (Gnome ancestry feats)
- Depends on: dc-cr-gnome-ancestry, dc-cr-gnome-weapon-familiarity, dc-cr-ancestry-feat-schedule

---

## Happy Path

### Availability
- [ ] `[NEW]` Gnome Weapon Expertise is selectable as a Gnome feat 13 when Gnome Weapon Familiarity is present.

### Proficiency cascade
- [ ] `[NEW]` When a class feature grants expert or greater proficiency in a weapon or weapon group, the same rank is granted to glaive.
- [ ] `[NEW]` The same cascade applies to kukri.
- [ ] `[NEW]` The same cascade applies to trained gnome weapons.

---

## Edge Cases
- [ ] `[NEW]` Only gnome weapons the character is already trained in receive the cascade.
- [ ] `[NEW]` The feat responds to later class-granted proficiency upgrades instead of snapshotting only at selection time.

## Failure Modes
- [ ] `[TEST-ONLY]` Characters without Gnome Weapon Familiarity cannot select or benefit from the feat.
- [ ] `[TEST-ONLY]` Non-class proficiency changes do not incorrectly trigger the cascade.

## Security acceptance criteria
- Security AC exemption: passive proficiency event handling only; no new route surface
