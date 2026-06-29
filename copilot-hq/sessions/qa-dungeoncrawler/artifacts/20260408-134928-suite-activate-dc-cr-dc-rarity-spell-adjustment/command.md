# Suite Activation: dc-cr-dc-rarity-spell-adjustment

- Agent: qa-dungeoncrawler
- Status: pending

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-08T13:49:28+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-dc-rarity-spell-adjustment"`**  
   This links the test to the living requirements doc at `features/dc-cr-dc-rarity-spell-adjustment/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-dc-rarity-spell-adjustment-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-dc-rarity-spell-adjustment",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-dc-rarity-spell-adjustment"`**  
   Example:
   ```json
   {
     "id": "dc-cr-dc-rarity-spell-adjustment-<route-slug>",
     "feature_id": "dc-cr-dc-rarity-spell-adjustment",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-dc-rarity-spell-adjustment",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-dc-rarity-spell-adjustment — DC Tables and Rarity Adjustments

**QA owner:** qa-dungeoncrawler
**Feature:** dc-cr-dc-rarity-spell-adjustment
**Depends on:** dc-cr-skill-system ✓
**KB reference:** none found (first DC-table test plan; cross-referenced by TC-CI-07 in dc-cr-creature-identification and TC-RK-14 in dc-cr-skills-recall-knowledge)

---

## Summary

20 TCs (TC-DC-01–20) covering: Simple DC table (proficiency-rank lookup), Level-based DC table (levels 0–25), Spell-level DC table (identify/recall), DC adjustment table (7 values −10→+10), rarity adjustments as DC adjustments (Uncommon/Rare/Unique), minimum proficiency rank gate (attempt allowed, success impossible), specific DC applications (Craft/Earn Income/Gather Information/Identify Magic/Learn a Spell/Recall Knowledge/NPC social), additive stacking, failure mode (rarity + level-based both additive), and ACL regression. All 20 TCs immediately activatable (dc-cr-skill-system already done).

---

## Test Cases

### Simple DC Table (TC-DC-01)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-DC-01 | Simple DC table returns correct DC by proficiency rank: Untrained=10, Trained=15, Expert=20, Master=30, Legendary=40 | Data validation | `GET /api/dc/simple?rank=trained` → `{"dc": 15}`; repeat for all 5 ranks; invalid rank returns 400 | authenticated |

### Level-Based DC Table (TC-DC-02–03)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-DC-02 | Level-based DC table covers all levels 0–25 with correct values per Table 10–4 | Data validation | `GET /api/dc/level?level=0` → expected DC value; `level=10` → expected DC; `level=25` → expected DC; `level=26` → 400/404 | authenticated |
| TC-DC-03 | Level-based DC table boundary: level below 0 rejected; level above 25 rejected | Data validation | `level=-1` and `level=26` both return validation error | authenticated |

### Spell Level DC Table (TC-DC-04)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-DC-04 | Spell-level DC table maps spell levels 0–10 to correct DCs for Identify Spell and Recall Knowledge | Data validation | `GET /api/dc/spell-level?spell_level=1` → expected DC; `spell_level=10` → expected DC; `spell_level=11` → 400 | authenticated |

### DC Adjustment Table (TC-DC-05–06)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-DC-05 | DC adjustment enum covers all 7 values: Incredibly Easy (−10), Very Easy (−5), Easy (−2), Normal (0), Hard (+2), Very Hard (+5), Incredibly Hard (+10) | Data validation | DC adjustment API or calculation function returns correct delta for each named adjustment; unrecognized adjustment name returns error | authenticated |
| TC-DC-06 | DC adjustment applied correctly to a base DC | Playwright / encounter | Base DC 20 + Hard (+2) = 22; Base DC 20 + Very Easy (−5) = 15; Base DC 20 + Normal (0) = 20 | authenticated |

### Rarity Adjustments (TC-DC-07–09)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-DC-07 | Uncommon rarity applies Hard adjustment (+2) to base DC | Data validation | Item/spell/creature with rarity=uncommon: calculated DC = base + 2 | authenticated |
| TC-DC-08 | Rare rarity applies Very Hard adjustment (+5) to base DC | Data validation | rarity=rare: calculated DC = base + 5 | authenticated |
| TC-DC-09 | Unique rarity applies Incredibly Hard adjustment (+10) to base DC | Data validation | rarity=unique: calculated DC = base + 10 | authenticated |

### Minimum Proficiency Rank Gate (TC-DC-10)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-DC-10 | Character below minimum proficiency rank can attempt but cannot succeed (max result = Failure); Crit Fail still possible | Playwright / encounter | DC has minimum_rank=expert; character is Trained: roll proceeds; roll result cannot be Success or Crit Success regardless of die roll; Failure and Crit Fail outcomes returned normally | authenticated |

### Specific DC Applications (TC-DC-11–17)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-DC-11 | Craft DC = item level DC from Table 10–5 + rarity adjustment from Table 10–6 | Playwright / downtime | Crafting common level-3 item: DC = level-3 table value; Crafting uncommon level-3 item: DC = level-3 value + 2 | authenticated |
| TC-DC-12 | Earn Income DC = settlement level → level-based DC from Table 10–5 | Playwright / downtime | Earn Income in level-5 settlement: DC = level-5 table value; level-10 settlement: DC = level-10 value | authenticated |
| TC-DC-13 | Gather Information DC = simple DC by availability; in-depth info raises DC | Playwright / encounter | Common subject Gather Info: DC = simple availability DC; rare subject: DC elevated by rarity adjustment | authenticated |
| TC-DC-14 | Identify Magic DC = level-based + rarity adjustment | Playwright / encounter | Level-4 common magic item: DC = level-4 value + 0; Level-4 rare magic item: DC = level-4 value + 5 | authenticated |
| TC-DC-15 | Learn a Spell DC = spell level DC + rarity adjustment | Playwright / downtime | Learning a common 3rd-level spell: DC = spell-level-3 DC; uncommon 3rd-level spell: DC = spell-level-3 + 2 | authenticated |
| TC-DC-16 | Recall Knowledge DC: simple DC for general info; level-based for creatures/hazards; rarity adjustment applied | Playwright / encounter | General Recall Knowledge: simple DC; Level-5 creature Recall Knowledge: level-5 DC; Level-5 uncommon creature: level-5 DC + 2 | authenticated |
| TC-DC-17 | NPC social DCs adjusted by attitude: Friendly=Easy(−2), Helpful=Very Easy(−5), Unfriendly=Hard(+2), Hostile=Very Hard(+5); fundamentally opposed=Incredibly Hard or impossible | Playwright / encounter | Friendly NPC Diplomacy: base DC − 2; Hostile NPC: base DC + 5; fundamentally opposed request: DC increased by +10 or blocked | authenticated |

### Edge Cases (TC-DC-18–19)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-DC-18 | Stacking adjustments combine additively: uncommon (+2) + hard (+2) = +4 total on base DC | Data validation | DC calculation with two adjustments: uncommon item that is Hard-rated context: base + 2 + 2 = base + 4; verified against calculation log | authenticated |
| TC-DC-19 | Stacking negative adjustments: Easy (−2) + Very Easy (−5) = −7 total; DC may not drop below 0 (or per minimum floor if defined) | Data validation | Two negative adjustments applied: combined delta = −7; if base DC = 5 and no floor: result = 0 or 1 (PM to confirm minimum floor) | authenticated |

### Failure Mode (TC-DC-20)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-DC-20 | Rarity adjustment and level-based DC both applied independently and additively (not exclusive) | Data validation | Level-5 rare creature: DC = level_dc(5) + 5 (rare Very Hard); neither overrides the other; calculation log shows both components | authenticated |

---

## Open PM questions / automation notes

1. **TC-DC-01 sub-ratings**: The AC mentions "with sub-ratings" for the Simple DC table — PM should confirm whether sub-ratings (e.g., Trained+, Expert−) exist and what their DC values are; automation needs a complete lookup table.
2. **TC-DC-19 minimum DC floor**: No AC specifies a minimum DC value (e.g., 0 or 5). PM should confirm whether there is a hard floor on final DC after all adjustments.
3. **TC-DC-17 fundamentally opposed "impossible"**: AC says "Incredibly Hard or impossible" — automation needs to know whether "impossible" means DC = ∞ (blocked at validation layer) or an extreme numeric DC. PM to confirm.
4. **TC-DC-10 minimum proficiency rank source**: The AC states "minimum proficiency ranks: characters below that rank cannot succeed" but does not specify which actions/DCs carry a minimum rank. PM/Dev to confirm where minimum_rank is stored (on the DC record, the spell, the action, or dynamically computed).

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-dc-rarity-spell-adjustment

## Gap analysis reference
- DB sections: core/ch10/Setting DCs (10 reqs)
- Depends on: dc-cr-skill-system ✓

---

## Happy Path

### Simple DC Table
- [ ] `[NEW]` Simple DC table implemented (Untrained = 10, Trained = 15, Expert = 20, Master = 30, Legendary = 40, with sub-ratings).

### Level-Based DC Table
- [ ] `[NEW]` Level-based DC table implemented (levels 0–25 mapped to DCs per Table 10–4).

### Spell Level DCs
- [ ] `[NEW]` Spell-level DCs implemented for Identify Spell / Recall Knowledge about spells (spell level → DC mapping).

### DC Adjustment Table
- [ ] `[NEW]` DC adjustments: Incredibly Easy (–10), Very Easy (–5), Easy (–2), Normal (0), Hard (+2), Very Hard (+5), Incredibly Hard (+10).
- [ ] `[NEW]` Rarity adjustments applied as DC adjustments: Uncommon = Hard (+2), Rare = Very Hard (+5), Unique = Incredibly Hard (+10).
- [ ] `[NEW]` Minimum proficiency ranks: characters below that rank cannot succeed (but can attempt and crit fail).

### Specific DC Applications
- [ ] `[NEW]` Craft DC: item's level from Table 10–5 + rarity adjustment from Table 10–6.
- [ ] `[NEW]` Earn Income DC: task level = settlement level → DC from Table 10–5.
- [ ] `[NEW]` Gather Information DC: simple DC based on availability; raise for in-depth info.
- [ ] `[NEW]` Identify Magic / Learn a Spell DC: level-based + rarity adjustment.
- [ ] `[NEW]` Recall Knowledge DC: simple DC for general info; level-based for creatures/hazards; rarity adjustment applied.
- [ ] `[NEW]` NPC social DCs adjusted by attitude: Friendly = Easy (–2), Helpful = Very Easy (–5), Unfriendly = Hard (+2), Hostile = Very Hard (+5); fundamentally opposed request = Incredibly Hard or impossible.

## Edge Cases
- [ ] `[NEW]` Stacking adjustments: multiple adjustments combine (e.g., uncommon + hard = +4 total).
- [ ] `[NEW]` Below minimum proficiency rank: attempt allowed but success impossible.

## Failure Modes
- [ ] `[TEST-ONLY]` Rarity adjustment and level-based DC used together: both applied independently (additive).

## Security acceptance criteria
- Security AC exemption: game-mechanic DC calculation; no new routes or user-facing input beyond existing encounter and downtime handlers
