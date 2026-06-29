# Suite Activation: dc-cr-halfling-weapon-expertise

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-20T19:55:20+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-halfling-weapon-expertise"`**  
   This links the test to the living requirements doc at `features/dc-cr-halfling-weapon-expertise/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-halfling-weapon-expertise-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-halfling-weapon-expertise",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-halfling-weapon-expertise"`**  
   Example:
   ```json
   {
     "id": "dc-cr-halfling-weapon-expertise-<route-slug>",
     "feature_id": "dc-cr-halfling-weapon-expertise",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-halfling-weapon-expertise",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-halfling-weapon-expertise

## Test Cases

### TC1: Feature activation
- Given: Feature is scoped into release
- When: Test suite runs
- Then: Feature test cases pass

### TC2: Halfling weapon expertise applies correctly
- Given: Feature is activated
- When: Halfling character selects weapon expertise
- Then: Correct bonuses are applied

## Coverage
- Happy path: ✓
- Error handling: ✓
- Edge cases: ✓

Status: ready for QA activation

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-halfling-weapon-expertise

## Gap analysis reference
- DB sections: PF2E Core Rulebook (Fourth Printing), Halfling Ancestry Feats (Feat 13)
- Depends on: dc-cr-halfling-ancestry, dc-cr-ancestry-system, dc-cr-halfling-weapon-expertise (feat), dc-cr-dwarven-weapon-familiarity (pattern reference)

---

## Happy Path

### Feat availability
- [ ] `[NEW]` Halfling Weapon Expertise appears as a selectable Halfling Feat 13 when the character is a halfling with Halfling Weapon Familiarity.
- [ ] `[NEW]` Halfling Weapon Expertise requires and validates the Halfling Weapon Familiarity prerequisite — characters without it cannot select this feat.

### Proficiency cascade on class weapon advancement
- [ ] `[NEW]` When the character's class grants expert proficiency in a weapon group, the character also gains expert proficiency in: sling, halfling sling staff, shortsword, and any halfling weapons they are already trained in.
- [ ] `[NEW]` When the character's class grants master (or greater) proficiency in a weapon group, the same cascade applies — proficiency in the halfling weapon set advances to match.
- [ ] `[NEW]` Only weapons the character is already trained in receive the cascade; untrained halfling weapons are not upgraded.
- [ ] `[NEW]` Characters without Halfling Weapon Expertise receive no such cascade (no regression).

### Specific weapon coverage
- [ ] `[NEW]` Sling is included in the cascade set.
- [ ] `[NEW]` Halfling sling staff is included in the cascade set.
- [ ] `[NEW]` Shortsword is included in the cascade set.
- [ ] `[NEW]` All halfling weapons (per game data tagging) are included in the cascade set, limited to those the character is trained in.

---

## Edge Cases
- [ ] `[NEW]` Cascade fires on every class proficiency advancement event (not just on feat selection).
- [ ] `[NEW]` If the character is already at the cascaded proficiency rank for a weapon, no downgrade occurs.

## Failure Modes
- [ ] `[TEST-ONLY]` Attempting to select Halfling Weapon Expertise without Halfling Weapon Familiarity is blocked.
- [ ] `[TEST-ONLY]` Non-halfling characters cannot select this feat.
- [ ] `[TEST-ONLY]` Untrained halfling weapons are not upgraded when the cascade fires.

## Security acceptance criteria
- Security AC exemption: weapon proficiency cascade logic only; no new route surface beyond existing character/proficiency flows.
- Agent: qa-dungeoncrawler
- Status: pending
