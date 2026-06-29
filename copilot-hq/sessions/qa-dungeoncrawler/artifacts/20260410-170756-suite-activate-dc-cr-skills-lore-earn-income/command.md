- Status: done
- Completed: 2026-04-11T02:43:58Z

# Suite Activation: dc-cr-skills-lore-earn-income

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-10T17:07:56+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-skills-lore-earn-income"`**  
   This links the test to the living requirements doc at `features/dc-cr-skills-lore-earn-income/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-skills-lore-earn-income-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-skills-lore-earn-income",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-skills-lore-earn-income"`**  
   Example:
   ```json
   {
     "id": "dc-cr-skills-lore-earn-income-<route-slug>",
     "feature_id": "dc-cr-skills-lore-earn-income",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-skills-lore-earn-income",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-skills-lore-earn-income

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Lore skill — Recall Knowledge (Lore), Earn Income
**KB reference:** Recall Knowledge (Lore) shares resolution logic with other skills' Recall Knowledge (dc-cr-skills-arcana-borrow-spell pattern); no additional dependency beyond dc-cr-skill-system. Earn Income employer-block timer follows the same pattern as Coerce immunity timer in dc-cr-skills-diplomacy-actions.
**Dependency note:** No external module dependencies beyond dc-cr-skill-system (already ✓). All 16 TCs are immediately activatable at Stage 0 — this is the cleanest dependency profile in the skills grooming batch.

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | PHPUnit functional | All Lore/Earn Income business logic: subcategory scoping, multi-subcategory independence, untrained use, downtime activity type, task-level DC, proficiency-tier caps, income table by degree, employer-block timer |
| `role-url-audit` | HTTP role audit | ACL regression — no new routes; existing downtime handler routes only |

---

## Test Cases

### Recall Knowledge (Lore)

### TC-LRE-01 — Lore Recall Knowledge is scoped to a specific subcategory
- **Suite:** module-test-suite
- **Description:** A Recall Knowledge check using Lore succeeds only for queries within the character's specific Lore subcategory (e.g., Mercantile Lore applies to trade/commerce; Sailing Lore applies to ships/navigation). Queries outside the subcategory are blocked or returned as out-of-scope.
- **Expected:** lore_subcategory = "Mercantile Lore" AND query_domain = "sailing" → out-of-scope response (not a skill check result); query_domain = "mercantile" → check resolves normally.
- **Notes to PM:** Confirm how query domains map to subcategory scope in the data model. Automation needs a deterministic domain→subcategory mapping.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-LRE-02 — Lore Recall Knowledge resolves identically to other skills' Recall Knowledge
- **Suite:** module-test-suite
- **Description:** The check resolution logic (roll, compare to DC, 4 degrees of success) for Lore Recall Knowledge is the same as for Arcana, Nature, Occultism, Religion, etc. Only the scope differs.
- **Expected:** lore_recall_knowledge result follows same 4-degree outcome structure as other Recall Knowledge actions; no special-cased resolution path.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-LRE-03 — Character may have multiple Lore subcategories, each tracked independently
- **Suite:** module-test-suite
- **Description:** Edge case — a character with two or more Lore subcategories (e.g., Mercantile Lore and Sailing Lore from different backgrounds/features) has each subcategory tracked as an independent skill entry. One subcategory's proficiency or check result does not affect another's.
- **Expected:** character.lore_subcategories = ["Mercantile Lore", "Sailing Lore"] → two independent entries; check for "Mercantile Lore" uses its own proficiency rank; does not bleed into "Sailing Lore."
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-LRE-04 — Untrained Lore can be used for Recall Knowledge within its subcategory
- **Suite:** module-test-suite
- **Description:** A character with Untrained proficiency in a Lore subcategory may still use it to Recall Knowledge for that subcategory (unlike most trained-only actions). The check rolls at the untrained modifier.
- **Expected:** lore_rank = untrained AND query_domain IN subcategory_scope → check allowed; rolls at untrained modifier.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Earn Income

### TC-LRE-05 — Earn Income is a downtime activity
- **Suite:** module-test-suite
- **Description:** Earn Income is classified as a downtime activity (not encounter or exploration).
- **Expected:** action_type = downtime.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-LRE-06 — Earn Income can use Lore, Crafting, or Performance skill
- **Suite:** module-test-suite
- **Description:** The Earn Income downtime activity accepts Lore, Crafting, or Performance as the resolving skill. The selected skill must match a relevant profession (e.g., Mercantile Lore for trade work, Crafting for artisan work, Performance for entertainment).
- **Expected:** skill IN [lore, crafting, performance] → action allowed with that skill; skill NOT IN those three → action blocked.
- **Notes to PM:** Confirm whether skill selection is player-choice at action time or constrained by a job/employer type. Automation needs a deterministic selection rule.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-LRE-07 — Earn Income DC determined by task level (1–20)
- **Suite:** module-test-suite
- **Description:** Each Earn Income task has a level (1–20) that determines the check DC. Higher task levels have higher DCs.
- **Expected:** task_level = N → DC = level_to_dc_table[N]; table covers levels 1–20.
- **Notes to PM:** Confirm the exact DC-per-level table to be used (PF2e standard table or a custom mapping). Automation needs the enumerated values.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-LRE-08 — Earn Income task level capped at character level
- **Suite:** module-test-suite
- **Description:** A character cannot select a task level higher than their own character level. Attempting to select a task above character level is blocked before the check.
- **Expected:** task_level > character_level → action blocked; error returned.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-LRE-09 — Earn Income task level cap: Trained characters capped at character level – 1
- **Suite:** module-test-suite
- **Description:** Edge case — characters with Trained proficiency in the chosen skill are further capped at character level – 1 (not character level) for Earn Income task selection. Expert and higher may use their full character level.
- **Expected:** skill_rank = trained → max_task_level = character_level - 1; skill_rank >= expert → max_task_level = character_level.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-LRE-10 — Earn Income Critical Success: next-tier daily income
- **Suite:** module-test-suite
- **Description:** On Critical Success, the character earns the daily income rate of the next tier above the task level (e.g., completing a level 3 task at Crit Success yields level 4 income).
- **Expected:** result = critical_success → daily_income = income_table[task_level + 1].
- **Notes to PM:** Confirm income table (GP per day per level) to be used. Automation needs enumerated values.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-LRE-11 — Earn Income Success: on-level daily income
- **Suite:** module-test-suite
- **Description:** On Success, the character earns the standard daily income for the task level.
- **Expected:** result = success → daily_income = income_table[task_level].
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-LRE-12 — Earn Income Failure: 0 income, no other penalty
- **Suite:** module-test-suite
- **Description:** On Failure, the character earns 0 income. No penalty beyond lost time; no employer block.
- **Expected:** result = failure → daily_income = 0; employer_blocked = false; no error condition.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-LRE-13 — Earn Income Critical Failure: 0 income + blocked from employer for ~1 week
- **Suite:** module-test-suite
- **Description:** Failure mode — on Critical Failure, the character earns 0 income AND is blocked from the same employer for approximately 1 week (they will not offer work during that period).
- **Expected:** result = critical_failure → daily_income = 0; employer_id.blocked_until = now + 7 days; further Earn Income attempts with same employer during block window → blocked.
- **Notes to PM:** Confirm whether employer block duration is exactly 7 days or a GM-variable window. Automation needs a deterministic value.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-LRE-14 — Earn Income Crit Fail block is employer-specific (other employers unaffected)
- **Suite:** module-test-suite
- **Description:** Failure mode edge case — the ~1-week employer block from a Critical Failure applies only to the specific employer where the failure occurred. Other employers are unaffected; the character may still Earn Income elsewhere.
- **Expected:** employer_A.blocked = true AND employer_B.blocked = false → Earn Income with employer_B succeeds normally.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-LRE-15 — Earn Income blocked above task level cap (hard block, not penalty)
- **Suite:** module-test-suite
- **Description:** Failure mode — attempting to select a task level above the character's cap (either character level or Trained cap at level – 1) returns a hard block. No check is rolled.
- **Expected:** task_level > max_allowed_task_level → action blocked; no check; error returned (not a –penalty result).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### ACL regression

### TC-LRE-16 — ACL regression: no new routes introduced by Lore/Earn Income
- **Suite:** role-url-audit
- **Description:** Lore and Earn Income implementation adds no new HTTP routes; existing downtime handler routes retain their ACL.
- **Expected:** HTTP 200 for authenticated player on existing downtime handler routes; HTTP 403 for anonymous.
- **Roles covered:** anonymous, authenticated player, admin
- **Status:** immediately activatable

---

## Dependency summary

All 16 TCs are immediately activatable at Stage 0. No external module dependencies beyond dc-cr-skill-system (already ✓).

---

## Notes to PM

1. **TC-LRE-01 (Lore scope mapping):** Confirm how query domains map to subcategory scope in the data model. Options: (a) each Lore subcategory has an explicit domain tag list, (b) domain matching is GM-adjudicated only. If (b), this TC becomes a note-to-dev rather than a strict automation check.
2. **TC-LRE-06 (Skill selection for Earn Income):** Confirm whether skill choice at action time is free (player picks Lore/Crafting/Performance) or constrained by job/employer type (e.g., a "Sailor" job can only use Sailing Lore or Sailing-relevant skills).
3. **TC-LRE-07/10/11 (DC and income tables):** The test plan assumes PF2e standard tables (Difficulty Classes by Level table + Income Earned table). Please confirm the exact GP-per-day values to be used so automation can verify exact outputs, not just relative comparisons.
4. **TC-LRE-09 (Trained cap):** AC says "Trained cap at level –1." Confirm this is a hard system rule (not GM-optional) and that Expert proficiency (not just Master/Legendary) already unlocks the full character-level cap.
5. **TC-LRE-13 (Employer block duration):** AC says "~1 week." Confirm whether this is exactly 7 in-game days, 7 real-world days, or a GM-variable value. System needs a deterministic scalar for automation.
6. **Staging note:** This is the cleanest feature in the skills grooming batch — 0 deferred TCs, 0 external dependencies. Recommend prioritizing for Stage 0 activation alongside dc-cr-skills-acrobatics-actions (also 0 deferred).

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-skills-lore-earn-income

## Gap analysis reference
- DB sections: core/ch04/Lore (Int) (REQs 1684–1687)
- Depends on: dc-cr-skill-system ✓

---

## Happy Path

### Recall Knowledge (Lore)
- [ ] `[NEW]` Lore Recall Knowledge functions identically to other skills' Recall Knowledge but is scoped to a specific Lore subcategory (e.g., Mercantile Lore, Sailing Lore).
- [ ] `[NEW]` Characters may have multiple Lore subcategories from background, ancestry, or class features.
- [ ] `[NEW]` Untrained Lore can be used to Recall Knowledge, but only for the chosen subcategory.

### Earn Income [Downtime]
- [ ] `[NEW]` Earn Income is a downtime activity using Lore (or Crafting/Performance for other professions).
- [ ] `[NEW]` DC determined by task level (1–20); max task level = character level (except Trained cap at level –1).
- [ ] `[NEW]` Degrees: Crit Success = next-tier daily income; Success = on-level daily income; Failure = 0 income but no penalty; Crit Failure = 0 income + blocked from that employer for ~1 week.

---

## Edge Cases
- [ ] `[NEW]` Multiple Lore subcategories stacked: each tracked independently.
- [ ] `[NEW]` Earn Income task level cap enforced per proficiency tier.

## Failure Modes
- [ ] `[TEST-ONLY]` Earn Income Crit Fail blocks same employer for ~1 week; other employers unaffected.
- [ ] `[TEST-ONLY]` Task above level cap: blocked (not just penalized).

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing downtime handlers
- Agent: qa-dungeoncrawler
- Status: pending
