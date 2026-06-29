# Suite Activation: dc-cr-vivacious-conduit

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-14T20:35:42+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-vivacious-conduit"`**  
   This links the test to the living requirements doc at `features/dc-cr-vivacious-conduit/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-vivacious-conduit-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-vivacious-conduit",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-vivacious-conduit"`**  
   Example:
   ```json
   {
     "id": "dc-cr-vivacious-conduit-<route-slug>",
     "feature_id": "dc-cr-vivacious-conduit",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-vivacious-conduit",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-vivacious-conduit

## Coverage summary
- AC items: 7 (3 happy path, 2 edge cases, 2 failure modes)
- Test cases: 5 (TC-VVC-01-05)
- Suites: playwright (feat progression, rest, medicine/healing)
- Security: AC exemption granted (existing healing routes only)

---

## TC-VVC-01 — Feat availability
- Description: Vivacious Conduit appears for eligible Gnome feat-9 selection.
- Suite: playwright/feat-progression
- Expected: feat picker includes `dc-cr-vivacious-conduit`
- AC: Availability

## TC-VVC-02 — 10-minute rest healing applied
- Description: Character completes a valid 10-minute rest.
- Suite: playwright/rest
- Expected: bonus healing = Constitution modifier × half level
- AC: Rest-based healing-1, Edge Cases-1

## TC-VVC-03 — Bonus stacks with Treat Wounds
- Description: Character receives Treat Wounds during the same rest cycle.
- Suite: playwright/medicine
- Expected: total healing = Treat Wounds result + Vivacious Conduit bonus
- AC: Rest-based healing-2

## TC-VVC-04 — No negative healing
- Description: Character has a negative Constitution modifier.
- Suite: playwright/rest
- Expected: bonus healing bottoms out at 0
- AC: Edge Cases-2

## TC-VVC-05 — No bonus outside valid rest
- Description: Character attempts to trigger the effect without a valid 10-minute rest.
- Suite: playwright/rest
- Expected: no Vivacious Conduit bonus is applied
- AC: Failure Modes-1, Failure Modes-2

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-vivacious-conduit

## Gap analysis reference
- DB sections: core/ch02 (Gnome ancestry feats)
- Depends on: dc-cr-gnome-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-rest-watch-starvation, dc-cr-skills-medicine-actions

---

## Happy Path

### Availability
- [ ] `[NEW]` Vivacious Conduit is selectable as a Gnome feat 9.

### Rest-based healing
- [ ] `[NEW]` After a 10-minute rest, the character regains HP equal to Constitution modifier × half level.
- [ ] `[NEW]` The healing stacks with Treat Wounds rather than replacing it.

---

## Edge Cases
- [ ] `[NEW]` Half level uses the system's standard rounding rule for the feat and stays consistent across all levels.
- [ ] `[NEW]` Negative Constitution modifiers do not produce negative healing.

## Failure Modes
- [ ] `[TEST-ONLY]` Characters without the feat receive only the baseline rest/Treat Wounds healing.
- [ ] `[TEST-ONLY]` The bonus is not applied outside a valid 10-minute rest window.

## Security acceptance criteria
- Security AC exemption: passive healing adjustment only; no new route surface
- Agent: qa-dungeoncrawler
- Status: pending
