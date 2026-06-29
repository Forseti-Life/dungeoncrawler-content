- Status: done
- Completed: 2026-04-12T11:35:54Z

# Suite Activation: dc-cr-treasure-by-level

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-12T03:46:03+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-treasure-by-level"`**  
   This links the test to the living requirements doc at `features/dc-cr-treasure-by-level/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-treasure-by-level-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-treasure-by-level",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-treasure-by-level"`**  
   Example:
   ```json
   {
     "id": "dc-cr-treasure-by-level-<route-slug>",
     "feature_id": "dc-cr-treasure-by-level",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-treasure-by-level",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-treasure-by-level

## Feature
Treasure-by-level tables, selling rules, and starting wealth from PF2E Core Rulebook Chapter 10.

## KB references
- None found for treasure or economy tables specifically.

## Dependencies
- dc-cr-economy (required — currency model, item prices)
- dc-cr-xp-award-system (required — level progression context)

Note: All TCs are conditional on dc-cr-economy. Automation cannot assert currency values without the currency/item-price model being implemented.

## Suite mapping
All TCs target the `dungeoncrawler-content` suite (game-mechanic data/logic).
No new routes or auth surfaces — Security AC exemption confirmed.

---

## Test Cases

### TC-TBL-01: Treasure table — 4-PC baseline completeness
- **Description:** Treasure-by-level table has an entry for every character level (1–20), each containing currency, permanent items, and consumables columns.
- **Suite:** dungeoncrawler-content
- **Expected:** 20 rows returned; each has `currency`, `permanent_items`, and `consumables` fields all non-null.
- **Roles:** N/A
- **Note to PM:** Exact currency/item values per level must be sourced from PF2E CRB Table 10–9 (or equivalent). BA should extract and confirm before Dev populates the table. Automation asserts structure and non-null values; value correctness requires confirmed data.

### TC-TBL-02: Treasure table — currency column composition
- **Description:** The currency column represents a combination of coins, gems, art objects, and lower-level items sold at half price.
- **Suite:** dungeoncrawler-content
- **Expected:** Treasure table metadata documents `currency_includes: ["coins", "gems", "art_objects", "half_price_items"]`; no item type listed as full-price in the currency column.
- **Roles:** N/A

### TC-TBL-03: Party size adjustment — additional currency per PC beyond 4
- **Description:** For each PC beyond 4 in the party, the appropriate additional currency per level is added using Party Size adjustment rules.
- **Suite:** dungeoncrawler-content
- **Expected:** `treasure_for_party(level, party_size=5)` returns `base_currency + 1x_per_pc_adjustment`; `party_size=6` returns `base + 2x`; etc.
- **Roles:** N/A
- **Note to PM:** Per-PC currency adjustment amounts per level are not specified in AC. Needs exact values from CRB Table 10–9 Party Adjustments column.

### TC-TBL-04: Selling standard items — half price
- **Description:** Standard items (not gems, art objects, or raw materials) sell for half their listed Price.
- **Suite:** dungeoncrawler-content
- **Expected:** `sell_item(type="standard", price=100)` returns `50`.
- **Roles:** N/A

### TC-TBL-05: Selling gems at full price
- **Description:** Gems sell at their full listed Price.
- **Suite:** dungeoncrawler-content
- **Expected:** `sell_item(type="gem", price=100)` returns `100`.
- **Roles:** N/A

### TC-TBL-06: Selling art objects at full price
- **Description:** Art objects sell at their full listed Price.
- **Suite:** dungeoncrawler-content
- **Expected:** `sell_item(type="art_object", price=100)` returns `100`.
- **Roles:** N/A

### TC-TBL-07: Selling raw materials at full price
- **Description:** Raw materials sell at their full listed Price.
- **Suite:** dungeoncrawler-content
- **Expected:** `sell_item(type="raw_material", price=100)` returns `100`.
- **Roles:** N/A

### TC-TBL-08: Buy/sell restricted to downtime
- **Description:** Characters can buy and sell items typically only during downtime; the system enforces or flags this restriction.
- **Suite:** dungeoncrawler-content
- **Expected:** `sell_item(phase="downtime")` succeeds; `sell_item(phase="encounter")` returns `flagged: true` or `blocked: true`; `sell_item(phase="exploration")` returns `flagged: true` or `blocked: true`.
- **Roles:** N/A
- **Note to PM:** AC says "typically only during downtime" and the edge case says "flagged or blocked per GM." This is ambiguous — is the system enforcement hard-blocked or soft-flagged with GM override? Needs PM decision for automation. Recommend soft-flag (GM override allowed) to match PF2E intent; see TC-TBL-09.

### TC-TBL-09 (Edge): Selling during encounter/exploration — flagged, not hard-blocked
- **Description:** Attempting to sell during encounter or exploration mode results in a flag/warning; a GM override may allow it.
- **Suite:** dungeoncrawler-content
- **Expected:** `sell_item(phase="encounter")` returns `{success: false, reason: "not_downtime", gm_override_available: true}` (or equivalent soft-flag pattern).
- **Roles:** N/A
- **Note to PM:** Conditional on PM decision from TC-TBL-08. If hard-block is chosen, expected behavior changes to `{success: false, blocked: true}`.

### TC-TBL-10 (Edge): Party size < 4 — proportional currency reduction
- **Description:** A party smaller than 4 PCs receives proportionally reduced currency (not a hard block).
- **Suite:** dungeoncrawler-content
- **Expected:** `treasure_for_party(level, party_size=3)` returns `base_currency - 1x_per_pc_adjustment`; result > 0 (not blocked).
- **Roles:** N/A
- **Note to PM:** Per-PC reduction amount needs same CRB table extraction as TC-TBL-03.

### TC-TBL-11: Starting wealth table — new/replacement character
- **Description:** New and replacement characters have a starting wealth by level table; querying any level returns the correct wealth entry.
- **Suite:** dungeoncrawler-content
- **Expected:** Starting wealth table has entries for all levels 1–20; each row has `currency` and optionally `item_budget` non-null.
- **Roles:** N/A
- **Note to PM:** Exact starting wealth values per level need BA extraction from CRB (Table 10–10 or equivalent).

### TC-TBL-12 (Failure): Selling standard item at full price — blocked or flagged
- **Description:** Attempting to sell a standard item at its full Price is rejected; system enforces half-price rule.
- **Suite:** dungeoncrawler-content
- **Expected:** `sell_item(type="standard", price=100, requested_price=100)` returns `{success: false, reason: "must_sell_at_half_price", corrected_value: 50}`.
- **Roles:** N/A

### TC-TBL-13 (Failure): Starting wealth for mid-campaign character — correct level row used
- **Description:** A level-5 replacement character uses the level-5 starting wealth row, not level 1.
- **Suite:** dungeoncrawler-content
- **Expected:** `starting_wealth(level=5)` returns the level-5 row; `starting_wealth(level=1)` returns the level-1 row; values differ.
- **Roles:** N/A

---

## Notes to PM

1. **Treasure table values (TC-TBL-01, TC-TBL-03, TC-TBL-10):** Exact currency, permanent items, consumables, and per-PC adjustment values are not specified in AC. BA must extract from PF2E CRB Table 10–9 before Dev populates the model. QA automation for value-correctness assertions is blocked until confirmed values are available.

2. **Starting wealth values (TC-TBL-11):** Same gap — exact values per level need BA extraction (CRB Table 10–10 or equivalent).

3. **Sell phase enforcement (TC-TBL-08, TC-TBL-09):** AC uses "typically" and "flagged or blocked per GM" — ambiguous between hard-block and soft-flag. PM decision needed for automation to assert the correct outcome.

4. **Dependencies:** Both dc-cr-economy and dc-cr-xp-award-system are listed as dependencies. Recommend sequencing dc-cr-treasure-by-level into the same release as or after both.

5. **Security AC exemption confirmed:** No new routes or user-facing input surfaces. All TCs are data-model/logic assertions only.

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-treasure-by-level

## Gap analysis reference
- DB sections: core/ch10/Treasure (6 reqs)
- Depends on: dc-cr-economy, dc-cr-xp-award-system

---

## Happy Path
- [ ] `[NEW]` Treasure per level (4-PC baseline) table implemented (currency + permanent items + consumables per level).
- [ ] `[NEW]` Currency column represents coins, gems, art objects, and lower-level items sold at half price.
- [ ] `[NEW]` For each PC beyond 4: additional currency per level added per Party Size adjustment rules.
- [ ] `[NEW]` Selling items: standard = half Price; gems, art objects, raw materials = full Price.
- [ ] `[NEW]` Characters can buy/sell items typically only during downtime.
- [ ] `[NEW]` New/replacement character starting wealth by level table implemented.

## Edge Cases
- [ ] `[NEW]` Selling during encounter or exploration (not downtime): flagged or blocked per GM.
- [ ] `[NEW]` Party size < 4: proportional currency reduction (not explicitly blocking).

## Failure Modes
- [ ] `[TEST-ONLY]` Selling a standard item at full price: blocked or flagged (only half price allowed).
- [ ] `[TEST-ONLY]` Starting wealth for mid-campaign character (level 5): uses level 5 row, not level 1.

## Security acceptance criteria
- Security AC exemption: game-mechanic economy logic; no new routes or user-facing input beyond existing character creation and downtime handlers
- Agent: qa-dungeoncrawler
- Status: pending
