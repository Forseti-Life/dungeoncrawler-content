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
