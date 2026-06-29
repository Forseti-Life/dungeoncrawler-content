- Status: done
- Completed: 2026-04-14T17:30:14Z

# Suite Activation: dc-cr-goblin-ancestry

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-14T17:26:32+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-goblin-ancestry"`**  
   This links the test to the living requirements doc at `features/dc-cr-goblin-ancestry/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-goblin-ancestry-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-goblin-ancestry",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-goblin-ancestry"`**  
   Example:
   ```json
   {
     "id": "dc-cr-goblin-ancestry-<route-slug>",
     "feature_id": "dc-cr-goblin-ancestry",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-goblin-ancestry",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-goblin-ancestry

## Coverage summary
- AC items: 9 (6 happy path, 2 edge cases, 2 failure modes)
- Test cases: 5 (TC-GOB-01-05)
- Suites: playwright (character creation, character sheet)
- Security: AC exemption granted (existing character routes only)

---

## TC-GOB-01 — Goblin ancestry selectable
- Description: Goblin appears in the ancestry picker during character creation.
- Suite: playwright/character-creation
- Expected: ancestry list includes Goblin
- AC: Character creation availability

## TC-GOB-02 — Core ancestry stats applied
- Description: Selecting Goblin assigns HP, size, speed, boosts, and flaw correctly.
- Suite: playwright/character-creation
- Expected: character ancestry data = {hp: 6, size: Small, speed: 25, boosts: [Dex, Cha, Free], flaw: Wis}
- AC: Core ancestry stats-1, Core ancestry stats-2, Core ancestry stats-3

## TC-GOB-03 — Heritage and feat tree linked
- Description: Goblin heritages and ancestry feats become available after choosing Goblin.
- Suite: playwright/character-creation
- Expected: goblin heritage list and goblin feat list are shown
- AC: Ancestry integration-1

## TC-GOB-04 — Goblin ancestry persists to sheet
- Description: Completed character shows Goblin ancestry data on the sheet.
- Suite: playwright/character-sheet
- Expected: ancestry block renders Goblin with correct stats and linked options
- AC: Ancestry integration-2

## TC-GOB-05 — Invalid ancestry payload rejected
- Description: Attempt to submit altered goblin stats from client.
- Suite: playwright/character-creation
- Expected: server rejects mismatched stat payload and reuses canonical ancestry values
- AC: Failure Modes-1, Failure Modes-2

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-goblin-ancestry

## Gap analysis reference
- DB sections: core/ch02 (Goblin ancestry, goblin heritages, goblin ancestry feats)
- Depends on: dc-cr-ancestry-system

---

## Happy Path

### Character creation availability
- [ ] `[NEW]` Goblin appears as a selectable ancestry in character creation.

### Core ancestry stats
- [ ] `[NEW]` Goblin ancestry grants 6 HP, Small size, and 25-foot Speed.
- [ ] `[NEW]` Goblin ancestry grants Dexterity and Charisma boosts plus one free boost.
- [ ] `[NEW]` Goblin ancestry applies Wisdom as the ancestry flaw.

### Ancestry integration
- [ ] `[NEW]` Goblin ancestry links to goblin heritages and the goblin ancestry feat tree.
- [ ] `[NEW]` Goblin ancestry data persists on the character record and is visible in the character sheet.

---

## Edge Cases
- [ ] `[NEW]` The free boost cannot duplicate an ancestry-assigned fixed boost if the character builder enforces PF2e boost restrictions.
- [ ] `[NEW]` Existing non-goblin characters cannot gain goblin ancestry-only feats without ancestry reassignment.

## Failure Modes
- [ ] `[TEST-ONLY]` Character creation rejects invalid ancestry stat payloads for goblin characters.
- [ ] `[TEST-ONLY]` Goblin ancestry cannot be applied to a character without re-running ancestry-dependent recalculation.

## Security acceptance criteria
- Security AC exemption: ancestry data modeling only; no new route surface beyond existing character flows
