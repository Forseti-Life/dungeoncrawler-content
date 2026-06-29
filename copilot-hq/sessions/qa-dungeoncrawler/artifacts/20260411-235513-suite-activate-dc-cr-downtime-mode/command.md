- Status: done
- Completed: 2026-04-12T00:37:24Z

# Suite Activation: dc-cr-downtime-mode

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-11T23:55:13+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-downtime-mode"`**  
   This links the test to the living requirements doc at `features/dc-cr-downtime-mode/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-downtime-mode-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-downtime-mode",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-downtime-mode"`**  
   Example:
   ```json
   {
     "id": "dc-cr-downtime-mode-<route-slug>",
     "feature_id": "dc-cr-downtime-mode",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-downtime-mode",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-downtime-mode

## Coverage summary
- AC items: ~14 (time scale, Earn Income, retraining, crafting basics, other downtime activities)
- Test cases: 10 (TC-DTM-01–10)
- Suites: playwright (downtime)
- Security: Mutates gold and character build — server-validated; audit-logged

---

## TC-DTM-01 — Downtime time scale: days, not rounds
- Description: Downtime tracks time in days; character allocates days to activities
- Suite: playwright/downtime
- Expected: time_unit = days; activity day allocation stored per activity
- AC: AC-001

## TC-DTM-02 — Earn Income: gold per day from task level + skill result
- Description: Earn Income check → gold earned per day from Earn Income table (task level + check result)
- Suite: playwright/downtime
- Expected: check result → gold = table_lookup(task_level, result_tier); daily gold applied
- AC: AC-002

## TC-DTM-03 — Earn Income: failure = no income; critical failure = fired for 1 week
- Description: Failed check → 0 gold that day; critical failure → character cannot use same task for 1 week
- Suite: playwright/downtime
- Expected: fail → gold += 0; crit fail → task blocked with 1-week cooldown
- AC: AC-002

## TC-DTM-04 — Retraining: 1 week per feat/skill increase
- Description: Retrain feat = 1 week downtime; retrain skill increase = 1 week; completion replaces old selection
- Suite: playwright/downtime
- Expected: retraining_days_required = 7 (1 week); on completion: old_feat removed, new_feat added
- AC: AC-003

## TC-DTM-05 — Crafting downtime: formula/tools prereq check; 4-day baseline; Alchemist free items
- Description: Crafting downtime requires formula + tools + rank; 4-day baseline; Alchemist's Infused Reagents = free alchemical items (no gold cost)
- Suite: playwright/downtime
- Expected: prereq validation; 4-day time block; Alchemist: items created at 0 gold cost via infused reagents
- AC: AC-004

## TC-DTM-06 — Subsist: Survival or Society check vs. environment DC
- Description: Subsist = Survival or Society check vs. local DC; success covers living expenses
- Suite: playwright/downtime
- Expected: check uses correct skill; environment DC sourced from location data; success → expenses covered
- AC: AC-005

## TC-DTM-07 — Treat Disease downtime: stage reduction on success
- Description: Treat Disease downtime check → disease stage reduced on success
- Suite: playwright/downtime
- Expected: success → disease.stage -= 1; failure → no change; checks use Medicine
- AC: AC-005

## TC-DTM-08 — Business income: uses appropriate table and skill
- Description: Running a business or crafting for sale uses appropriate income table and skill
- Suite: playwright/downtime
- Expected: business income = table_lookup by skill result; correct skill applied per activity type
- AC: AC-005

## TC-DTM-09 — Gold mutations are server-validated and audit-logged
- Description: All downtime gold changes (earn income, crafting cost, subsist expenses) are server-validated and logged
- Suite: playwright/downtime
- Expected: gold mutations pass through server validation; audit log entry created for each change
- AC: Security AC

## TC-DTM-10 — Build mutations (retraining) are server-validated
- Description: Retraining feat/skill changes are server-validated; no client-side bypass
- Suite: playwright/downtime
- Expected: retraining request processed server-side; invalid retraining (e.g., missing prereq days) rejected
- AC: Security AC

### Acceptance criteria (reference)

# Acceptance Criteria: Downtime Mode
# Feature: dc-cr-downtime-mode

## AC-001: Downtime Time Scale
- Given a session transitions to downtime, when downtime begins, then time is tracked in days (not rounds or minutes)
- Given a downtime period begins, when the character chooses an activity, then the system records how many days are allocated to that activity

## AC-002: Earn Income
- Given a character uses the Earn Income downtime action, when the check is resolved, then the character earns gold per day based on the task level and skill check result (using the Earn Income table)
- Given a failed Earn Income check, when the result is below DC, then the character earns no income for that day
- Given a critical failure on Earn Income, when applied, then the character is fired and cannot attempt the same task again for a week

## AC-003: Retraining
- Given a character wants to retrain a feat, when retraining begins, then it requires 1 week of downtime per feat level
- Given a character wants to retrain a skill increase, when retraining is applied, then it requires 1 week of downtime
- Given retraining completes, when downtime ends, then the old selection is replaced by the new selection

## AC-004: Crafting (Basic)
- Given Crafting downtime begins for an item, when the process starts, then the character must have the formula, appropriate tools, and meet the skill rank requirement
- Given 4 days of Crafting downtime pass, when progress is calculated, then the character pays half the item price and the item is available (additional days reduce the remaining cost)
- Given the character has the Alchemist's Infused Reagents, when the daily Crafting resolution runs, then alchemical items are prepared at no gold cost (only time)

## AC-005: Other Downtime Activities
- Given a character uses the Subsist downtime action, when resolved, then they make a Survival or Society check against the local environment DC to cover living expenses
- Given a character uses the Treat Disease downtime action, when the check is resolved, then the disease stage is reduced on success
- Given a character runs a business or crafts for sale, when downtime is tracked, then the income calculation uses the appropriate table and skill

## Security acceptance criteria

- Security AC exemption: Downtime activities modify character economic state (gold) and character build (retraining). All mutations are server-validated and audit-logged to prevent gold duplication exploits.

## Roadmap section

- Roadmap: Core Rulebook
- Chapter: Chapter 9: Playing the Game (Downtime)
