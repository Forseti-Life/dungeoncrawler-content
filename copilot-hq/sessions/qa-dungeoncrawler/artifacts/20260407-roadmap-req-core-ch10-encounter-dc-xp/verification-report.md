# Verification Report — Core Ch10: Encounter Building, Setting DCs, XP, Treasure

**Inbox item:** `20260407-roadmap-req-core-ch10-encounter-dc-xp`
**REQ range:** 2311–2345 (34 REQs)
**Verifier:** qa-dungeoncrawler
**Date:** 2026-04-07
**Verdict:** BLOCK

---

## Summary

| Section | REQs | PASS | PARTIAL | BLOCK |
|---|---|---|---|---|
| Encounter Building | 2311–2317 | 2 | 2 | 3 |
| Setting DCs | 2318–2330 | 2 | 1 | 10 |
| Creature Identification | 2331 | 0 | 0 | 1 |
| XP and Advancement | 2332–2339 | 0 | 1 | 7 |
| Treasure | 2340–2345 | 0 | 2 | 4 |
| **TOTAL** | **34** | **4** | **6** | **24** |

---

## Section: Encounter Building (REQs 2311–2317)

### REQ 2311 — Encounter threat tiers: Trivial ≤40 XP, Low 60, Moderate 80, Severe 120, Extreme 160
**Verdict: PASS**
`EncounterGeneratorService::XP_BUDGETS` const (line 89):
```php
'trivial' => 40, 'low' => 60, 'moderate' => 80, 'severe' => 120, 'extreme' => 160
```

### REQ 2312 — XP budget is 4-PC baseline; adjust per extra/missing PC via Character Adjustment value
**Verdict: PARTIAL**
`EncounterGeneratorService::calculateXpBudget()` multiplies `$base_xp × $party_size` (scales proportionally). PF2e uses a specific per-PC flat adjustment value by tier — not a direct multiplier. Foundation exists; exact Character Adjustment per-PC value table not implemented.

### REQ 2313 — Implement encounter budget system using the table
**Verdict: PASS**
Covered by `XP_BUDGETS` const (same table as REQ 2311). `generateEncounter()` calls `calculateXpBudget()` which uses this table.

### REQ 2314 — Creature XP cost based on level vs party level (level-difference table)
**Verdict: BLOCK**
Creature `xp_value` fields are hardcoded per creature (15, 30, 40, 60 observed in stub arrays). The PF2e level-difference table (party-4=10, party-3=15, party-2=20, party-1=30, party=40, party+1=60, party+2=80, party+3=120, party+4=160) is not implemented as a lookup. Creature XP is not dynamically computed from party_level − creature_level.
**Suggested feature:** `dc-cr-encounter-creature-xp-table`

### REQ 2315 — Creatures >4 levels below = trivial; >4 above = too dangerous (no XP defined)
**Verdict: BLOCK**
No level-variance guard in `EncounterGeneratorService`. Creatures of any level can be added to an encounter without rejection or trivial flagging. Part of `dc-cr-encounter-creature-xp-table`.

### REQ 2316 — Party level determines encounter budget; support level-variance handling
**Verdict: PARTIAL**
`generateEncounter()` accepts `party_level` param and passes to `calculateXpBudget()`, but `calculateXpBudget()` ignores `party_level` in the budget calculation (only uses tier × party_size). Level-variance handling (per-creature XP based on level diff) not wired.

### REQ 2317 — PCs behind party level earn double XP until caught up
**Verdict: BLOCK**
No such mechanic. CharacterLevelingService is milestone-only (PM decision 2026-03-08 removed XP system). Part of `dc-cr-encounter-creature-xp-table` or `dc-cr-xp-award-system`.

---

## Section: Setting DCs (REQs 2318–2330)

### REQ 2318 — Simple DC table (levels 1–20)
**Verdict: PASS**
`CombatCalculator::SIMPLE_DC` (line 177) has all 20 entries matching PF2e Table 10-5:
`1→15, 2→16, 3→18, 4→19, 5→20, 6→22, 7→23, 8→24, 9→26, 10→27, 11→28, 12→30, 13→31, 14→32, 15→34, 16→35, 17→36, 18→38, 19→39, 20→40`
`CombatCalculator::getSimpleDC(int $level)` exposes it with capping at 20.

### REQ 2319 — Level-Based DC table
**Verdict: PASS**
PF2e Table 10-5 is both the "Simple DC" and "Level-Based DC" table. `CombatCalculator::SIMPLE_DC` / `getSimpleDC()` fully covers this requirement.

### REQ 2320 — Spell Level DCs (Identify Spell, Recall Knowledge about spells)
**Verdict: BLOCK**
No spell-level DC table in codebase. PF2e spell level DCs: 1→15, 2→18, 3→20, 4→23, 5→26, 6→28, 7→31, 8→34, 9→36, 10→39.
**Suggested feature:** `dc-cr-dc-rarity-spell-adjustment`

### REQ 2321 — DC Adjustment table (trivial/easy/standard/hard/very hard/incredibly hard)
**Verdict: PARTIAL**
`CombatCalculator::TASK_DC` has difficulty tiers: trivial=10, low=15, moderate=20, high=25, extreme=30, incredible=40. However, PF2e DC Adjustment is a modifier applied to a base DC (−10, −5, −2, 0, +2, +5, +10). The TASK_DC provides fixed DCs by tier, not additive modifiers. Foundation adjacent; exact DC adjustment modifier table absent.

### REQ 2322 — Rarity DC adjustments (Uncommon +2, Rare +5, Unique +10)
**Verdict: BLOCK**
No rarity DC adjustment method or constant in codebase. No `RARITY_DC_MODIFIER` or equivalent. Part of `dc-cr-dc-rarity-spell-adjustment`.

### REQ 2323 — Tasks may require minimum proficiency rank; below-rank characters cannot succeed (but can critically fail)
**Verdict: BLOCK**
No proficiency minimum rank gating in `calculateSkillCheck()` (CharacterCalculator) or any check resolution path. Part of `dc-cr-skills-calculator-hardening` (previously identified).

### REQ 2324 — Proficiency rank guidelines by level range (informational)
**Verdict: N/A**
Informational GM guideline, no enforcement code required. Considered passing — no gap.

### REQ 2325 — Craft DC: item's level → Table 10-5; apply rarity adjustment
**Verdict: BLOCK**
No Craft DC calculation. DowntimePhaseHandler `craft` case is a stub. Part of `dc-cr-skills-crafting-actions`.

### REQ 2326 — Earn Income DC: task level = settlement level → Table 10-5
**Verdict: BLOCK**
DowntimePhaseHandler `earn_income` is a stub. Part of `dc-cr-skills-lore-earn-income` (previously identified).

### REQ 2327 — Gather Information DC: simple DC based on availability
**Verdict: BLOCK**
No Gather Information DC service or handler. Part of `dc-cr-skills-diplomacy-actions` (previously identified).

### REQ 2328 — Identify Magic / Learn Spell DC: level-based + rarity adjustment
**Verdict: BLOCK**
No identify magic DC calculation. Part of `dc-cr-decipher-identify-learn` (previously identified).

### REQ 2329 — Recall Knowledge DC: simple DC for general; level-based for creatures/hazards; rarity adjustment
**Verdict: BLOCK**
`recall_knowledge` is registered in CanonicalActionRegistryService (line 64) but routes to generic `applyCharacterStateChanges` with no DC resolution logic. Part of `dc-cr-skills-recall-knowledge` (previously identified).

### REQ 2330 — NPC social DCs: adjust by attitude (friendly=−2, helpful=−5, unfriendly=+2, hostile=+5, opposed=incredibly hard)
**Verdict: BLOCK**
No NPC attitude model or social DC adjustment in codebase. Part of `dc-cr-skills-diplomacy-actions`.

---

## Section: Creature Identification (REQ 2331)

### REQ 2331 — Recall Knowledge skill by creature trait (Aberration→Occultism, Beast→Nature, etc.)
**Verdict: BLOCK**
`recall_knowledge` action registered in CanonicalActionRegistryService but with no skill–trait routing table. Creature types and their associated Recall Knowledge skills (Arcana for Constructs/Dragons/Elementals, Nature for Animals/Beasts/Fungi/Plants, Occultism for Aberrations/Oozes/Undead, Religion for Celestials/Fiends, Society for Humanoids) not defined anywhere.
**Suggested feature:** `dc-cr-creature-identification`

---

## Section: Experience Points and Advancement (REQs 2332–2339)

> **PM decision 2026-03-08 context:** CharacterLevelingService comment explicitly states milestone-based leveling — `dc-cr-xp-rewards` dependency removed. REQ 2336 (story-based leveling) aligns with this decision. REQs 2332–2335 and 2337–2339 are BLOCK per book spec but may be intentionally deferred.

### REQ 2332 — XP threshold: 1,000 XP to gain a level; subtract 1,000 on level-up
**Verdict: BLOCK**
CharacterLevelingService uses `milestoneReady` flag only. No XP storage, threshold check, or 1000-XP level trigger. PM decision 2026-03-08 explicitly removed XP system.
**Suggested feature:** `dc-cr-xp-award-system` (PM must confirm if this deferred or out-of-scope)

### REQ 2333 — All party members receive same XP from any encounter or accomplishment
**Verdict: BLOCK**
No party-wide XP award. QuestRewardService `grantXP()` is a stub (TODO comment). No encounter-completion XP distribution.

### REQ 2334 — Trivial encounters normally grant 0 XP
**Verdict: BLOCK**
No XP system implemented (see REQ 2332). `xp_value` exists in EncounterGeneratorService creature stubs but is not awarded to characters.

### REQ 2335 — Advancement speed variants: Fast 800 XP, Standard 1,000 XP, Slow 1,200 XP
**Verdict: BLOCK**
No advancement speed setting. XP system absent.

### REQ 2336 — Story-based leveling: ignore XP; level after ~3–4 sessions at milestones
**Verdict: PARTIAL**
`CharacterLevelingService` milestone flag (`milestoneReady`) matches this pattern. GM sets `milestoneReady=true` via admin action. However, no "3–4 session" guidance or story-milestone documentation is exposed to GM interface. Implementation aligns with the spirit.

### REQ 2337 — Accomplishment XP (minor/moderate/major XP awards)
**Verdict: BLOCK**
No accomplishment XP system. QuestRewardService handles quest rewards but uses pre-configured reward amounts (not the minor/moderate/major PF2e accomplishment bands).

### REQ 2338 — Moderate and major accomplishments grant Hero Point to instrumental PC
**Verdict: BLOCK**
Hero Points are implemented (`heroic_recovery_all_points` in EncounterPhaseHandler, `hero_point_reroll`). But accomplishment→Hero Point award linkage is absent.

### REQ 2339 — Creature XP from Table 10-2; Hazard XP from Table 10-14
**Verdict: BLOCK**
No XP award tables for creatures or hazards. `xp_value` in EncounterGeneratorService creature stubs is not dynamically assigned from a level-relative table.

---

## Section: Treasure (REQs 2340–2345)

### REQ 2340 — Treasure per level (4-PC baseline table, levels 1–20)
**Verdict: BLOCK**
`ContentGenerator::generateTreasureHoard()` uses generic minor/moderate/major buckets with dice-rolled currency amounts. PF2e per-level treasure table (e.g., level 1 = 175 gp total, permanent items of specific levels, etc.) is not implemented.
**Suggested feature:** `dc-cr-treasure-by-level`

### REQ 2341 — Currency column = coins, gems, art objects, lower-level items at half price
**Verdict: BLOCK**
No currency breakdown definition. ContentGenerator dice-rolls gold without treasure-type categorization.

### REQ 2342 — Extra currency for party size adjustment
**Verdict: BLOCK**
No party-size treasure adjustment table. `generateTreasureHoard()` takes `$level` and `$hoard_type` only.

### REQ 2343 — Selling: standard items at half price; gems/art/raw materials at full price
**Verdict: PARTIAL**
QuestRewardService `grantGold()` is a stub (TODO). No marketplace or selling mechanic exists. Half-price rule not enforced anywhere.

### REQ 2344 — Characters can buy/sell items only during downtime
**Verdict: PARTIAL**
DowntimePhaseHandler has craft/earn_income stubs. Conceptual downtime phase exists. No buy/sell marketplace; downtime restriction not enforced.

### REQ 2345 — New/replacement character starting wealth by level
**Verdict: BLOCK**
No starting wealth table. CharacterManager doesn't set level-appropriate starting gold for new characters.

---

## PASS/BLOCK Summary by REQ

| REQ | Section | Verdict | Service / Gap |
|---|---|---|---|
| 2311 | Encounter Building | **PASS** | EncounterGeneratorService::XP_BUDGETS |
| 2312 | Encounter Building | **PARTIAL** | calculateXpBudget() scales by party_size but no per-PC adjustment value |
| 2313 | Encounter Building | **PASS** | EncounterGeneratorService::XP_BUDGETS |
| 2314 | Encounter Building | **BLOCK** | No level-diff XP table; values hardcoded |
| 2315 | Encounter Building | **BLOCK** | No level-range guard |
| 2316 | Encounter Building | **PARTIAL** | party_level accepted but not used in level-variance |
| 2317 | Encounter Building | **BLOCK** | No double-XP mechanic for lagging PCs |
| 2318 | Setting DCs | **PASS** | CombatCalculator::SIMPLE_DC + getSimpleDC() |
| 2319 | Setting DCs | **PASS** | CombatCalculator::SIMPLE_DC (same table) |
| 2320 | Setting DCs | **BLOCK** | No spell-level DC table |
| 2321 | Setting DCs | **PARTIAL** | TASK_DC tiers present; DC modifier table missing |
| 2322 | Setting DCs | **BLOCK** | No rarity DC adjustment constant/method |
| 2323 | Setting DCs | **BLOCK** | No proficiency minimum rank gating |
| 2324 | Setting DCs | N/A | Informational guideline |
| 2325 | Setting DCs | **BLOCK** | Craft DC absent (stub only) |
| 2326 | Setting DCs | **BLOCK** | Earn Income DC absent (stub only) |
| 2327 | Setting DCs | **BLOCK** | Gather Information DC absent |
| 2328 | Setting DCs | **BLOCK** | Identify Magic DC absent |
| 2329 | Setting DCs | **BLOCK** | Recall Knowledge DC absent (action registered, no logic) |
| 2330 | Setting DCs | **BLOCK** | NPC social DCs / attitude model absent |
| 2331 | Creature Identification | **BLOCK** | recall_knowledge has no creature-trait→skill routing |
| 2332 | XP/Advancement | **BLOCK** | Milestone-only; XP removed per PM decision 2026-03-08 |
| 2333 | XP/Advancement | **BLOCK** | No party-wide XP award; grantXP() is TODO stub |
| 2334 | XP/Advancement | **BLOCK** | No XP system |
| 2335 | XP/Advancement | **BLOCK** | No advancement speed variants |
| 2336 | XP/Advancement | **PARTIAL** | Milestone flag aligns with story-based leveling |
| 2337 | XP/Advancement | **BLOCK** | No accomplishment XP categories |
| 2338 | XP/Advancement | **BLOCK** | No accomplishment→Hero Point linkage |
| 2339 | XP/Advancement | **BLOCK** | No XP award from creatures or hazards |
| 2340 | Treasure | **BLOCK** | No per-level treasure table |
| 2341 | Treasure | **BLOCK** | No currency breakdown definition |
| 2342 | Treasure | **BLOCK** | No party-size treasure adjustment |
| 2343 | Treasure | **PARTIAL** | grantGold() stub only; half-price rule absent |
| 2344 | Treasure | **PARTIAL** | Downtime phase exists; buy/sell not enforced |
| 2345 | Treasure | **BLOCK** | No starting wealth by level |

---

## Suggested Feature Pipeline (PM triage)

| Feature ID | REQs Covered | Priority |
|---|---|---|
| `dc-cr-encounter-creature-xp-table` | 2314, 2315, 2316, 2317 | HIGH — encounter balance broken without level-relative XP |
| `dc-cr-dc-rarity-spell-adjustment` | 2320, 2322, 2328 | HIGH — DC system incomplete for magic items and spells |
| `dc-cr-creature-identification` | 2331 | MEDIUM — recall_knowledge registered but does nothing |
| `dc-cr-xp-award-system` | 2332–2335, 2337–2339 | LOW/DEFERRED — PM decision 2026-03-08 removed XP; PM must confirm scope |
| `dc-cr-treasure-by-level` | 2340–2342, 2345 | MEDIUM — treasure generation uses placeholder dice tables |

**Already in pipeline (previously identified):**
- `dc-cr-skills-calculator-hardening` — covers REQ 2323 (proficiency rank gating)
- `dc-cr-skills-crafting-actions` — covers REQ 2325
- `dc-cr-skills-lore-earn-income` — covers REQ 2326
- `dc-cr-skills-diplomacy-actions` — covers REQs 2327, 2330
- `dc-cr-decipher-identify-learn` — covers REQ 2328, 2329
- `dc-cr-skills-recall-knowledge` — covers REQ 2329

---

## Codebase Evidence

| File | Relevant Lines | Status |
|---|---|---|
| `EncounterGeneratorService.php` | 89–94 (XP_BUDGETS), 199–221 (calculateXpBudget), 259–270 (creature xp_value stubs) | PASS/PARTIAL/BLOCK |
| `CombatCalculator.php` | 177–182 (SIMPLE_DC), 187–194 (TASK_DC), 220–244 (getSimpleDC/getTaskDC) | PASS |
| `CharacterLevelingService.php` | Line 10–11 (milestone comment), Line 54–79 (milestoneReady), Line 115–116 (milestone gate) | PARTIAL (2336) |
| `QuestRewardService.php` | 114–116 (grantXP TODO stub), 253–258 (grantXP body: TODO only) | BLOCK |
| `ContentGenerator.php` | 263–277 (generateTreasureHoard), 289–330 (generateCurrency: dice only), 332–365 (generateHoardItems) | BLOCK |
| `CanonicalActionRegistryService.php` | 64–70 (recall_knowledge registered, no DC logic) | BLOCK |
