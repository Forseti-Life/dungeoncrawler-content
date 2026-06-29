# Suite Activation: dc-cr-economy

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-10T02:12:41+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-economy"`**  
   This links the test to the living requirements doc at `features/dc-cr-economy/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-economy-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-economy",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-economy"`**  
   Example:
   ```json
   {
     "id": "dc-cr-economy-<route-slug>",
     "feature_id": "dc-cr-economy",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-economy",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-economy

## Coverage summary
- AC items: 14 (10 happy path, 2 edge cases, 2 failure modes)
- Test cases: 14 (TC-ECO-01–14)
- Suites: playwright (character creation, inventory, downtime)
- Security: AC exemption granted (no new routes)

---

## TC-ECO-01 — Currency exchange rates
- Description: cp/sp/gp/pp convert at 10cp=1sp, 10sp=1gp, 10gp=1pp
- Suite: playwright/inventory
- Expected: currency.convert(10, cp) = 1 sp; convert(10, sp) = 1 gp; convert(10, gp) = 1 pp
- AC: Currency-1

## TC-ECO-02 — Price "—" items cannot be purchased
- Description: Items with Price = "—" are blocked from purchase
- Suite: playwright/inventory
- Expected: purchase attempt on Price="—" item returns error = not_for_sale
- AC: Currency-2, Failure-1

## TC-ECO-03 — Price 0 items are free
- Description: Items with Price = 0 can be obtained without gold deduction
- Suite: playwright/inventory
- Expected: purchase of Price=0 item: gold unchanged, item added to inventory
- AC: Currency-2

## TC-ECO-04 — Character starting wealth
- Description: Level 1 characters start with 15 gp (= 150 sp)
- Suite: playwright/character-creation
- Expected: new character at level 1: gold = 15 gp (or equivalent in lower denominations)
- AC: Currency-3

## TC-ECO-05 — Coin Bulk calculation (floor division)
- Description: 1,000 coins = 1 Bulk; uses floor division (not ceiling)
- Suite: playwright/inventory
- Expected: 999 coins = 0 Bulk; 1000 coins = 1 Bulk; 1999 coins = 1 Bulk
- AC: Currency-4, Edge-2

## TC-ECO-06 — Hireling rates: unskilled vs. skilled
- Description: Unskilled hireling has +0 all skills; skilled hireling has +4 in specialty, +0 elsewhere
- Suite: playwright/downtime
- Expected: hireling.unskilled.skill_bonus = 0; hireling.skilled.specialty_bonus = 4; hireling.skilled.other_bonus = 0
- AC: Services-1

## TC-ECO-07 — Hireling rates double in danger
- Description: Hireling rates double when adventuring into dangerous areas
- Suite: playwright/downtime
- Expected: hireling.daily_cost when in_danger = true doubles the base rate
- AC: Services-2

## TC-ECO-08 — Spellcasting services: uncommon + material cost
- Description: Spellcasting services are uncommon; cost = table price + material component cost; surcharge for uncommon/long-cast spells
- Suite: playwright/downtime
- Expected: service.spellcasting.availability = uncommon; total_cost includes materials + surcharge where applicable
- AC: Services-3, Failure-2

## TC-ECO-09 — Subsist action fulfills subsistence
- Description: Subsist action in downtime satisfies subsistence standard (no coin cost)
- Suite: playwright/downtime
- Expected: downtime.subsist rolls Survival/Society; success = subsistence_met = true; no gold deducted
- AC: Services-4

## TC-ECO-10 — Animal purchase and rental prices
- Description: Animals available at prices per Table 6-17; rental price distinct from purchase
- Suite: playwright/inventory
- Expected: animal_catalog entries have price and rental_per_day fields matching table values
- AC: Animals-1

## TC-ECO-11 — Non-combat-trained animal panics in combat
- Description: Non-combat-trained animal becomes frightened 4 + fleeing when combat begins
- Suite: playwright/encounter
- Expected: animal_companion.combat_trained = false → on combat_start: condition.frightened = 4, condition.fleeing = true
- AC: Animals-2

## TC-ECO-12 — Combat-trained animal does not panic
- Description: Combat-trained animal functions normally in combat
- Suite: playwright/encounter
- Expected: animal_companion.combat_trained = true → no frightened or fleeing condition on combat_start
- AC: Animals-3

## TC-ECO-13 — Barding: armor tracking with size scaling
- Description: Barding tracked as armor; Strength-based; no runes; Price/Bulk scale by animal size
- Suite: playwright/inventory
- Expected: barding item has armor_type field; rune_slots = 0; price and bulk scale with mount.size
- AC: Animals-4

## TC-ECO-14 — Selling restricted to downtime
- Description: Purchase/sell transactions blocked during encounter or exploration phase
- Suite: playwright/encounter
- Expected: sell_item during encounter or exploration phase returns error = downtime_only
- AC: Edge-1

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-economy

## Gap analysis reference
- DB sections: core/ch06 — Currency and Economy (6), Services and Economy (5), Animals and Mounts (5)
  Note: These sections were already covered in dc-cr-equipment-ch06 and flipped to in_progress.
  This feature focuses on the economy/services/animals subsystem as a distinct service layer.
- Depends on: dc-cr-equipment-system ✓, dc-cr-character-creation

---

## Happy Path

### Currency
- [ ] `[EXTEND]` Currency system supports cp/sp/gp/pp with standard exchange rates (10cp=1sp, 10sp=1gp, 10gp=1pp).
- [ ] `[EXTEND]` Items with Price "—" cannot be purchased; Price 0 items are free.
- [ ] `[EXTEND]` Character starting wealth = 15 gp (150 sp) at level 1.
- [ ] `[EXTEND]` Bulk of coins: 1,000 = 1 Bulk (rounded down) tracked in inventory.

### Services
- [ ] `[EXTEND]` Hireling rate table implemented: unskilled = +0 all skills; skilled = +4 specialty, +0 otherwise.
- [ ] `[EXTEND]` Hireling rates double when adventuring into danger.
- [ ] `[EXTEND]` Spellcasting services: uncommon; cost = table price + material component cost; surcharges for uncommon/long-cast spells.
- [ ] `[EXTEND]` Subsist action can fulfill subsistence standard (replaces coin cost).

### Animals and Mounts
- [ ] `[EXTEND]` Animals have purchase and rental prices per Table 6-17.
- [ ] `[EXTEND]` Non-combat-trained animals panic in combat (frightened 4 + fleeing).
- [ ] `[EXTEND]` Combat-trained animals do not panic.
- [ ] `[EXTEND]` Barding tracked as armor: Strength modifier-based; no runes; Price/Bulk scale by size.

---

## Edge Cases
- [ ] `[EXTEND]` Selling during encounter or exploration: flagged (downtime-only purchase/sell).
- [ ] `[EXTEND]` Coin Bulk calculation: floor(coins / 1000), not ceiling.

## Failure Modes
- [ ] `[TEST-ONLY]` Item with Price "—" presented for purchase: blocked.
- [ ] `[TEST-ONLY]` Services at uncommon spell level: surcharge applied (not blocked).

## Security acceptance criteria
- Security AC exemption: game-mechanic economy and services logic; no new routes or user-facing input beyond existing character creation and downtime handlers
- Agent: qa-dungeoncrawler
- Status: pending
