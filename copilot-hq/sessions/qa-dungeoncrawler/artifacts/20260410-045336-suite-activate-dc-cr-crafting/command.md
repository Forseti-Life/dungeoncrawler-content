- Status: done
- Completed: 2026-04-10T17:25:50Z

# Suite Activation: dc-cr-crafting

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-10T04:53:36+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-crafting"`**  
   This links the test to the living requirements doc at `features/dc-cr-crafting/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-crafting-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-crafting",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-crafting"`**  
   Example:
   ```json
   {
     "id": "dc-cr-crafting-<route-slug>",
     "feature_id": "dc-cr-crafting",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-crafting",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-crafting

## Coverage summary
- AC items: ~18 (prerequisites, time/cost, skill check outcomes, formula system, alchemist daily crafting, downtime dependency)
- Test cases: 12 (TC-CRF-01–12)
- Suites: playwright (downtime, character creation)
- Security: Crafting mutates gold and inventory — server-validated; gold deduction and item grant are atomic

---

## TC-CRF-01 — Crafting prerequisites: formula, tools, skill rank, item level
- Description: Character must have formula, required tools/workshop, minimum Crafting rank, and character level ≥ item level
- Suite: playwright/downtime
- Expected: missing any prerequisite → rejected with specific feedback naming the missing prereq
- AC: AC-001

## TC-CRF-02 — Crafting time: 4-day commitment, half-price at start
- Description: Crafting begins: character pays half price; 4-day minimum; additional days reduce remaining half via daily rate
- Suite: playwright/downtime
- Expected: day 1: gold -= half_price; remaining_cost = half_price; each additional day reduces remaining_cost by daily_rate; item complete when remaining_cost ≤ 0
- AC: AC-002

## TC-CRF-03 — Crafting check: critical success halves time
- Description: Critical success on initial check finishes item in half standard time
- Suite: playwright/downtime
- Expected: crit success → crafting_days_required = ceil(standard_time / 2)
- AC: AC-003

## TC-CRF-04 — Crafting check: failure loses initial materials (4 days, no item)
- Description: Failed check: 4 days spent, item not completed, initial material cost lost
- Suite: playwright/downtime
- Expected: failure → item not created; gold not refunded; time spent = 4 days
- AC: AC-003

## TC-CRF-05 — Crafting check: critical failure ruins materials (full cost lost)
- Description: Critical failure: all materials ruined; full cost lost
- Suite: playwright/downtime
- Expected: crit failure → item not created; gold -= full_price (not half); materials = destroyed
- AC: AC-003

## TC-CRF-06 — Formula system: formulas added to formula book on acquisition
- Description: Acquiring a formula adds it to character's formula book/list; free formulas from class (Alchemist/Inventor) auto-granted on level-up
- Suite: playwright/character-creation
- Expected: formula acquisition adds to formula_list; class free formulas appear at correct levels
- AC: AC-004

## TC-CRF-07 — Formula rarity gate: uncommon/rare requires appropriate source
- Description: Uncommon/Rare formulas require source permission to learn
- Suite: playwright/character-creation
- Expected: uncommon formula without source → blocked; with source (feat, background, GM grant) → allowed
- AC: AC-004

## TC-CRF-08 — Alchemist: Advanced Alchemy creates free items at daily prep
- Description: Alchemist creates alchemical items equal to 2 × proficiency bonus at daily prep; no gold cost; from formula list only
- Suite: playwright/downtime
- Expected: daily_prep → alchemical_items_created = 2 × proficiency_bonus; gold unchanged; only formula list items available
- AC: AC-005

## TC-CRF-09 — Alchemist: Quick Alchemy uses reagent to create instant item
- Description: Quick Alchemy (1 action, field use): spend 1 reagent → create 1 alchemical item immediately
- Suite: playwright/encounter
- Expected: Quick Alchemy action costs 1 action; reagent_count -= 1; item created instantly; available for immediate use that turn
- AC: AC-005

## TC-CRF-10 — Crafting requires downtime context (not adventuring actions)
- Description: Crafting cannot be initiated outside downtime mode
- Suite: playwright/encounter
- Expected: crafting attempt in encounter or exploration mode → rejected with "requires downtime" feedback
- AC: AC-006

## TC-CRF-11 — Downtime integration: time tracking fires correctly for crafting
- Description: When downtime mode active and crafting selected, downtime time tracking integrates correctly
- Suite: playwright/downtime
- Expected: crafting days deducted from downtime_days_available; time integration with dc-cr-downtime-mode
- AC: AC-006

## TC-CRF-12 — Atomic gold/item operations: no duplication on error
- Description: Gold deduction and item grant are atomic; partial failure does not grant item without deducting gold
- Suite: playwright/downtime
- Expected: if item grant fails mid-operation, gold deduction is rolled back; if gold deduction fails, no item granted; no inconsistent state
- AC: Security AC

### Acceptance criteria (reference)

# Acceptance Criteria: Crafting System
# Feature: dc-cr-crafting

## AC-001: Crafting Prerequisites
- Given a character attempts to craft an item, when prerequisites are validated, then the character must have: the item's formula, required tools/workshop, minimum Crafting skill rank (trained for basic, expert for uncommon, etc.), and meet the item's level requirement (character level ≥ item level)
- Given the character lacks a prerequisite, when crafting is attempted, then the attempt is rejected with specific feedback on which prerequisite is missing

## AC-002: Crafting Time and Cost
- Given crafting begins, when the initial 4-day commitment is made, when the day check passes, then the character pays half the item's gold Price and the item is crafted at base quality
- Given additional days beyond 4 are spent, when each day resolves, then the remaining cost (the other half) is reduced by the character's daily rate (based on Crafting check result and income table)
- Given the full cost is paid down, when the final day's calculation shows remaining cost ≤ 0, then the item is complete at no additional gold

## AC-003: Skill Check at Start
- Given the character begins crafting, when the initial check is resolved, then a Crafting skill check against the item's Crafting DC (typically 15 + item level) determines quality
- Given a critical success, when applied, then the item is finished in half the standard time
- Given a failure, when the 4 days are spent, then the item is not completed and the character loses the initial material cost
- Given a critical failure, when applied, then the materials are ruined and the full cost is lost

## AC-004: Formula System
- Given formulas exist as learnable content, when a character acquires a formula, then it is added to their formula book/list
- Given a character levels up, when free formulas are granted (per class — Alchemist, Inventor), then those formulas are automatically available
- Given a formula is uncommon or rare, when the character tries to learn it, then they must have an appropriate source or permission

## AC-005: Alchemist Daily Crafting
- Given an Alchemist uses Advanced Alchemy, when the daily preparation resolves, then they create a number of alchemical items (bombs, elixirs, mutagens) equal to 2 × their proficiency bonus at no gold cost, only from their formula list
- Given the Alchemist spends an Alchemist's Tools action in the field, when Quick Alchemy is used, when a reagent is spent, then 1 alchemical item is created instantly for immediate use

## AC-006: Downtime Dependency
- Given Crafting uses downtime days, when crafting is initiated, when it is not a downtime context, then the crafting action is rejected (crafting requires extended time, not adventuring actions)
- Given downtime mode is active (dc-cr-downtime-mode), when Crafting is selected, then the time-tracking integration fires correctly

## Security acceptance criteria

- Security AC exemption: Crafting modifies gold (economic state) and adds items to inventory. All crafting resolution is server-validated; gold deduction and item grant are atomic operations to prevent duplication.

## Roadmap section

- Roadmap: Core Rulebook
- Chapter: Chapter 4: Skills (Crafting skill) / Chapter 9: Downtime
- Depends on: dc-cr-downtime-mode
- Agent: qa-dungeoncrawler
- Status: pending
