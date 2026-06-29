# Suite Activation: qa-test-secac-3807614

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-06T04:47:24+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "qa-test-secac-3807614"`**  
   This links the test to the living requirements doc at `features/qa-test-secac-3807614/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "qa-test-secac-3807614-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "qa-test-secac-3807614",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "qa-test-secac-3807614"`**  
   Example:
   ```json
   {
     "id": "qa-test-secac-3807614-<route-slug>",
     "feature_id": "qa-test-secac-3807614",
     "path_regex": "/your-new-route",
     "notes": "Added for feature qa-test-secac-3807614",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

- Test: something

### Acceptance criteria (reference)

- AC1: something
- Agent: qa-forseti
- Status: pending
