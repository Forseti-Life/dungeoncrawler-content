# Test Plan Design: dc-cr-crafting

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T16:28:41+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-crafting/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-crafting "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/dungeoncrawler/suite.json`
- Do NOT edit `org-chart/sites/dungeoncrawler.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

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
