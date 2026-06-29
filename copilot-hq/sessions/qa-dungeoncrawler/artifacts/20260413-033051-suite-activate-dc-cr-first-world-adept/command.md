- Status: done
- Completed: 2026-04-13T05:37:31Z

# Suite Activation: dc-cr-first-world-adept

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-13T03:30:51+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-first-world-adept"`**  
   This links the test to the living requirements doc at `features/dc-cr-first-world-adept/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-first-world-adept-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-first-world-adept",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-first-world-adept"`**  
   Example:
   ```json
   {
     "id": "dc-cr-first-world-adept-<route-slug>",
     "feature_id": "dc-cr-first-world-adept",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-first-world-adept",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-first-world-adept

## Coverage summary
- AC items: 7 (3 happy path, 2 edge cases, 2 failure modes)
- Test cases: 5 (TC-FWA-01-05)
- Suites: playwright (character creation, feat progression, spellcasting)
- Security: AC exemption granted (existing feat/spell routes only)

---

## TC-FWA-01 — Prerequisite-gated feat availability
- Description: First World Adept appears only for characters with a primal innate spell.
- Suite: playwright/feat-progression
- Expected: feat locked before prerequisite, selectable after prerequisite is present
- AC: Availability, Failure Modes-1

## TC-FWA-02 — Faerie fire granted correctly
- Description: Selecting the feat grants faerie fire as a 2nd-level primal innate spell.
- Suite: playwright/character-sheet
- Expected: innate spell entry exists with `faerie fire`, rank 2, primal, uses_per_day = 1
- AC: Granted spells-1

## TC-FWA-03 — Invisibility granted correctly
- Description: Selecting the feat grants invisibility as a 2nd-level primal innate spell.
- Suite: playwright/character-sheet
- Expected: innate spell entry exists with `invisibility`, rank 2, primal, uses_per_day = 1
- AC: Granted spells-2

## TC-FWA-04 — Uses reset daily
- Description: Spend both innate spells, then complete daily preparation.
- Suite: playwright/spellcasting
- Expected: both spell uses reset to 1/day available
- AC: Edge Cases-2, Failure Modes-2

## TC-FWA-05 — Non-primal innate spell alone does not qualify
- Description: Character has innate spell access from another tradition only.
- Suite: playwright/feat-progression
- Expected: feat remains unavailable until a primal innate spell source exists
- AC: Edge Cases-1

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-first-world-adept

## Gap analysis reference
- DB sections: core/ch02 (Gnome ancestry feats)
- Depends on: dc-cr-gnome-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-spellcasting, one existing primal innate spell source

---

## Happy Path

### Availability
- [ ] `[NEW]` First World Adept is selectable as a Gnome feat 9 only when the character already has at least one primal innate spell.

### Granted spells
- [ ] `[NEW]` Selecting the feat grants `faerie fire` as a 2nd-level primal innate spell usable once per day.
- [ ] `[NEW]` Selecting the feat grants `invisibility` as a 2nd-level primal innate spell usable once per day.

---

## Edge Cases
- [ ] `[NEW]` The prerequisite is satisfied by any valid primal innate spell source (heritage or feat), not by prepared spellcasting alone.
- [ ] `[NEW]` Both granted spells reset on daily preparation with other once-per-day innate spells.

## Failure Modes
- [ ] `[TEST-ONLY]` Characters without a primal innate spell cannot select First World Adept.
- [ ] `[TEST-ONLY]` The granted spells are not castable more than once per day each.

## Security acceptance criteria
- Security AC exemption: ancestry feat grant only; no new route surface beyond existing character and spell flows
- Agent: qa-dungeoncrawler
- Status: pending
