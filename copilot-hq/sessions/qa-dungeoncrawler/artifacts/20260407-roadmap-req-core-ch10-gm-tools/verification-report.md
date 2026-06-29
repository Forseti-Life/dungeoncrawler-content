# Verification Report — Core Ch10: Environment, Hazards, NPC Social, Resting, Creature Identification

**Inbox item:** `20260407-roadmap-req-core-ch10-gm-tools`
**REQ range:** 2331, 2346–2397 (sections: Creature Identification, Resting and Daily Preparations, Environment, Hazards, NPC Social Mechanics)
**Verifier:** qa-dungeoncrawler
**Date:** 2026-04-07
**Verdict:** BLOCK

---

## Summary

| Section | REQs | PASS | PARTIAL | BLOCK |
|---|---|---|---|---|
| Creature Identification | 2331 | 0 | 0 | 1 |
| Resting and Daily Preparations | 2346–2349 | 0 | 0 | 4 |
| Environment | 2350–2372 | 1 | 2 | 20 |
| Hazards | 2373–2396 | 2 | 3 | 19 |
| NPC Social Mechanics | 2397 | 0 | 1 | 0 |
| **TOTAL** | **53** | **3** | **6** | **44** |

---

## Section: Creature Identification (REQ 2331)

### REQ 2331 — Recall Knowledge skill by creature trait (Aberration→Occultism, Beast→Nature, etc.)
**Verdict: BLOCK**
`recall_knowledge` is registered in `CanonicalActionRegistryService` (line 64) with executor `GameplayActionProcessor::applyCharacterStateChanges` — a generic pass-through. No creature-trait→Recall Knowledge skill routing table exists anywhere in the codebase. The PF2e mapping (Arcana: Constructs/Dragons/Elementals/Magical Beasts; Nature: Animals/Beasts/Fungi/Plants/Spirits; Occultism: Aberrations/Oozes/Undead; Religion: Celestials/Fiends/Monitors; Society: Humanoids) is not implemented.
**Suggested feature:** `dc-cr-creature-identification`

---

## Section: Resting and Daily Preparations (REQs 2346–2349)

> Context: REQs 2301–2310 (prior audit) confirmed `long_rest` and `downtime_rest` are implemented in `DowntimePhaseHandler`. The following REQs are *extensions* to that base.

### REQ 2346 — Watch duration by party size
**Verdict: BLOCK**
No watch-duration table. `processLongRest()` in `DowntimePhaseHandler` (lines 236+) restores HP and spell slots but contains no watch-schedule tracking, party-size–based watch rotation, or watch duration variable. PF2e watch durations: 3 PCs = one 8-hour watch, 4+ PCs = watches proportionally shorter.
**Suggested feature:** `dc-cr-rest-watch-starvation`

### REQ 2347 — Track starvation/thirst as environmental hazards
**Verdict: BLOCK**
No starvation/thirst tracking in any service. `game_state` has no `food_days`, `water_days`, or equivalent fields observed in any handler.

### REQ 2348 — Without water: immediate fatigue; (Con mod + 1) days then 1d4 damage/hour, unhealable until quenched
**Verdict: BLOCK**
No water-deprivation tracking or damage application. `ConditionManager::applyCondition()` could support `fatigued` from this path, but no water-tracking trigger exists.

### REQ 2349 — Without food: immediate fatigue; (Con mod + 1) days then 1 damage/day, unhealable until fed
**Verdict: BLOCK**
No food-deprivation tracking. Same gap as REQ 2348. `AdvanceDay` case in DowntimePhaseHandler does not decrement food/water counters.

---

## Section: Environment (REQs 2350–2372)

### REQ 2350 — Environmental damage categories (falling, suffocation, fire, cold, electricity, etc.)
**Verdict: PARTIAL**
`HPManager::applyFallDamage()` covers falling damage (REQ 2246). `ConditionManager` has condition states (dying, unconscious). No explicit environmental damage category enumeration; fire/cold/electricity environmental sources not modeled separately from spell damage. Foundation partial.

### REQ 2351 — Terrain/environment features with proficiency check bands
**Verdict: PARTIAL**
`TerrainGeneratorService` defines terrain types with `difficult_terrain: bool` flags (stone, sand, lava, water, ice, rubble, undergrowth-equivalent). `ExplorationPhaseHandler::calculateTravelSpeed()` applies `difficult` (×0.5) and `greater_difficult` (×0.25) multipliers (lines 791–798). Gap: no proficiency check bands defined per terrain type; no Athletics/Acrobatics check triggered on movement through terrain.

### REQ 2352 — Temperature effects (cold, heat: fatigue → damage per hour)
**Verdict: BLOCK**
No temperature system. No heat/cold environmental damage accumulation per hour.

### REQ 2353 — Bogs: shallow = difficult, deep = greater difficult, magical = hazardous
**Verdict: BLOCK**
`TerrainGeneratorService` has water terrain types but no `bog` entry. No bog-specific terrain classification.

### REQ 2354 — Ice: uneven ground AND difficult terrain
**Verdict: BLOCK**
`TerrainGeneratorService` has `ice` type with `difficult_terrain: TRUE` (line 76) but does not model `uneven_ground` as a second simultaneous property. No combined terrain flag.

### REQ 2355 — Snow: shallow = difficult, deep = greater difficult; deep may be uneven ground
**Verdict: BLOCK**
No `snow` terrain type in `TerrainGeneratorService`.

### REQ 2356 — Sand: packed = normal, loose shallow = difficult, loose deep = uneven ground
**Verdict: BLOCK**
`TerrainGeneratorService` has a single `sand` entry with `difficult_terrain: TRUE`. No sand depth sub-classification (packed vs. loose).

### REQ 2357 — Rubble: difficult; dense rubble = uneven ground
**Verdict: BLOCK**
`TerrainGeneratorService` has `rubble` in `calculateTravelSpeed()` multipliers (×0.5) but no `uneven_ground` property for dense rubble.

### REQ 2358 — Undergrowth: light = difficult; heavy = greater difficult (automatic cover); thorns = hazardous
**Verdict: BLOCK**
No `undergrowth` terrain type. `TerrainGeneratorService` doesn't model undergrowth density sub-types.

### REQ 2359 — Slopes: gentle = normal; steep = requires Athletics Climb check; flat-footed while climbing
**Verdict: BLOCK**
No slope terrain variant. `ExplorationPhaseHandler` does not trigger Climb checks for steep slopes.

### REQ 2360 — Narrow surface: requires Balance (Acrobatics); flat-footed; fall risk on hit/save-fail
**Verdict: BLOCK**
No narrow surface terrain type or Balance check trigger.

### REQ 2361 — Uneven ground: requires Balance (Acrobatics); flat-footed; fall risk on hit/save-fail
**Verdict: BLOCK**
`uneven_ground` property not modeled as a standalone terrain category with triggered Acrobatics check.

### REQ 2362 — Avalanche damage: major/massive bludgeoning; Reflex save: success = half; crit success = avoid burial
**Verdict: BLOCK**
No avalanche event handler.

### REQ 2363 — Burial: restrained, minor bludgeoning/minute, possible cold damage, suffocation Fortitude
**Verdict: BLOCK**
No burial condition or suffocation system.

### REQ 2364 — Rescue digging: Athletics check, 5×5 ft per 4 min (2 min on crit); halved with tools
**Verdict: BLOCK**
No rescue dig mechanic.

### REQ 2365 — Collapses: major/massive bludgeoning + burial; no spread without structural failure
**Verdict: BLOCK**
No structural collapse event handler.

### REQ 2366 — Wind: circumstance penalty to auditory Perception checks (strength-based)
**Verdict: BLOCK**
No wind system.

### REQ 2367 — Wind: circumstance penalty to physical ranged attacks; powerful winds = impossible
**Verdict: BLOCK**
No wind modifier applied to attack rolls in `CombatEngine`.

### REQ 2368 — Flying in wind: difficult terrain moving against; Maneuver in Flight check; blown away on crit fail
**Verdict: BLOCK**
No wind-vs-flying interaction. `EncounterPhaseHandler` has `fly` action but no wind modifier.

### REQ 2369 — Ground movement in strong wind: Athletics check; crit fail = knocked back+prone; Small −1, Tiny −2
**Verdict: BLOCK**
No wind Athletics check for ground movement.

### REQ 2370 — Underwater visibility: up to 240 ft (clear), as low as 10 ft (murky)
**Verdict: BLOCK**
No underwater visibility model. `RoomConnectionAlgorithm` sets static `perception_dc = 15 + level_number` but no water clarity variable.

### REQ 2371 — Swimming against current: difficult terrain or greater
**Verdict: BLOCK**
No current direction/speed tracking. `calculateTravelSpeed()` has no `swimming_against_current` terrain variant.

### REQ 2372 — Current displacement: creature moved in current direction at current speed each turn
**Verdict: BLOCK**
No current displacement mechanic in `EncounterPhaseHandler` or `ExplorationPhaseHandler`.

---

## Section: Hazards (REQs 2373–2396)

> **Important context:** `ContentRegistry::validateTrap()` (lines 412–425) confirms traps must have `stealth_dc` and `disable_dc` fields. `RoomStateService` hides traps until `detected = TRUE`. These provide a thin but real foundation.

### REQ 2373 — Hazards have a Stealth DC for detection
**Verdict: PASS**
`ContentRegistry::validateTrap()` requires `stealth_dc` (numeric). `RoomStateService` hides traps until detected. `EncounterPhaseHandler::processSeek()` uses `stealth_dcs` map (line 2196).

### REQ 2374 — No minimum proficiency: all PCs auto-roll secret Perception vs Stealth DC when entering area
**Verdict: PARTIAL**
`ExplorationPhaseHandler::processSearch()` rolls Perception vs `search_dc` (default 15) and reveals hidden entities on success. However: (a) this is a manual Search action, not an auto-roll on room entry; (b) secret trait not honored — result is reported back to player; (c) `search_dc` is a single room-level DC, not per-hazard `stealth_dc`.

### REQ 2375 — Minimum proficiency hazards: only actively Searching characters with qualifying rank attempt
**Verdict: BLOCK**
No minimum proficiency check in `processSearch()` or `revealHiddenEntities()`. Any character can attempt detection.

### REQ 2376 — Detect magic reveals magical hazards (no min proficiency); doesn't allow disabling
**Verdict: BLOCK**
`detect_magic` activity exists in `ExplorationPhaseHandler` activity list but is not wired to reveal hazards. No magical hazard flag checked.

### REQ 2377 — Passive triggers: hazard activates if not detected
**Verdict: PARTIAL**
`RoomStateService` hides traps until detected, and `processInteract()` has a comment "Future: check for traps, locked doors" (line 541). No automatic trigger on room entry for undetected hazards is currently wired.

### REQ 2378 — Active triggers: only fire if PC explicitly takes that action
**Verdict: PARTIAL**
`processInteract()` would be the hook for active triggers. It delegates to generic narration only. No active-trigger check for traps on interact actions.

### REQ 2379 — Simple hazard: one reaction, resolves in one step
**Verdict: BLOCK**
No simple/complex hazard type field in validated trap schema. `ContentRegistry::validateTrap()` only requires `stealth_dc` and `disable_dc`.

### REQ 2380 — Complex hazard: starts encounter/initiative; performs routine each round
**Verdict: BLOCK**
No `is_complex` field in trap schema. No hazard initiative join or routine execution in `EncounterPhaseHandler`.

### REQ 2381 — Complex hazard routine: preprogrammed actions per round
**Verdict: BLOCK**
No routine array in any hazard data structure.

### REQ 2382 — If PCs in encounter when complex hazard triggers, hazard joins at Stealth initiative
**Verdict: BLOCK**
No initiative join for hazards in `CombatEngine::startEncounter()` or `EncounterPhaseHandler`.

### REQ 2383 — Hazard Reset condition: auto-reset after time; manual reset requires listed steps
**Verdict: BLOCK**
No `reset_condition` field in validated hazard/trap schema.

### REQ 2384 — Disable hazard: 2-action activity, skill check vs hazard's disable DC
**Verdict: PASS**
`ContentRegistry::validateTrap()` requires `disable_dc` (or `disable.thievery_dc`). Foundation exists. Note: no handler in `ExplorationPhaseHandler` currently executes a disable check — the schema exists but the action is not wired.

### REQ 2385 — Critical failure on disable attempt triggers the hazard
**Verdict: BLOCK**
No disable action handler (see REQ 2384 note). No crit-fail trigger logic.

### REQ 2386 — Multiple successes for complex hazards; crit success = two successes on one component
**Verdict: BLOCK**
No multi-success disable tracking.

### REQ 2387 — Minimum proficiency may apply to disabling
**Verdict: BLOCK**
No proficiency minimum field in trap schema or disable handler.

### REQ 2388 — Must have detected (or been told about) hazard before disabling
**Verdict: PARTIAL**
`RoomStateService` hides traps until `detected = TRUE`. A disable action could check this flag. No disable handler exists to enforce it, but the detection flag is present.

### REQ 2389 — Hazards have AC, saving throw modifiers, Hardness, HP, Broken Threshold
**Verdict: BLOCK**
`ContentRegistry::validateTrap()` validates only `stealth_dc` and `disable_dc`. No `ac`, `save_bonus`, `hardness`, `max_hp`, or `broken_threshold` fields validated or required.

### REQ 2390 — Hazard at/below BT = broken (non-functional); at 0 HP = destroyed
**Verdict: BLOCK**
No HP tracking for hazards. `HPManager` is character-only; no hazard HP system.

### REQ 2391 — Hitting hazard typically triggers it unless destroyed outright
**Verdict: BLOCK**
No hazard HP/attack resolution in `CombatEngine`. Hazards are not treated as valid attack targets.

### REQ 2392 — Hazards immune to effects targeting non-objects unless specified
**Verdict: BLOCK**
No hazard immunity flags in trap schema.

### REQ 2393 — Magical hazards have spell level and counteract DC; counteract per Ch.9 rules
**Verdict: BLOCK**
No `spell_level` or `counteract_dc` field in trap schema.

### REQ 2394 — Crit fail on counteract triggers hazard
**Verdict: BLOCK**
No counteract action handler for hazards.

### REQ 2395 — Hazard XP awarded on overcoming (disable/avoid/endure); once per hazard per party
**Verdict: BLOCK**
No hazard XP award. QuestRewardService `grantXP()` is a TODO stub. No per-hazard-per-party tracking.

### REQ 2396 — Hazard XP table
**Verdict: BLOCK**
No hazard XP table in codebase.

---

## Section: NPC Social Mechanics (REQ 2397)

### REQ 2397 — NPC attitude modifies social skill check DCs using standard DC adjustment table
**Verdict: PARTIAL**
`NpcPsychologyService` has `ATTITUDE_LADDER` const (`helpful → friendly → indifferent → unfriendly → hostile`) and `shiftAttitude()` method. Attitude is stored per-NPC and affects narration prompts in `RoomChatService` (`$npc_attitude` passed to AI GM). However, no DC modifier method translates attitude to a numeric DC adjustment (PF2e: friendly=−2, helpful=−5, unfriendly=+2, hostile=+5). The social DC adjustment table from REQ 2330 (ch10) is not implemented as a lookup.

---

## PASS/PARTIAL/BLOCK Summary by REQ

| REQ | Section | Verdict | Evidence |
|---|---|---|---|
| 2331 | Creature Identification | **BLOCK** | recall_knowledge: no trait→skill routing |
| 2346 | Resting | **BLOCK** | No watch duration by party size |
| 2347 | Resting | **BLOCK** | No starvation/thirst tracking |
| 2348 | Resting | **BLOCK** | No water-deprivation damage |
| 2349 | Resting | **BLOCK** | No food-deprivation damage |
| 2350 | Environment | **PARTIAL** | Fall damage implemented; other env damage absent |
| 2351 | Environment | **PARTIAL** | TerrainGeneratorService difficult_terrain flags; no Acrobatics/Athletics triggers |
| 2352 | Environment | **BLOCK** | No temperature system |
| 2353 | Environment | **BLOCK** | No bog terrain type |
| 2354 | Environment | **BLOCK** | Ice has difficult_terrain but no uneven_ground |
| 2355 | Environment | **BLOCK** | No snow terrain type |
| 2356 | Environment | **BLOCK** | Sand: single entry, no depth sub-types |
| 2357 | Environment | **BLOCK** | Rubble: no uneven_ground for dense |
| 2358 | Environment | **BLOCK** | No undergrowth terrain type |
| 2359 | Environment | **BLOCK** | No slope terrain or Climb triggers |
| 2360 | Environment | **BLOCK** | No narrow surface terrain |
| 2361 | Environment | **BLOCK** | No uneven_ground terrain category |
| 2362 | Environment | **BLOCK** | No avalanche handler |
| 2363 | Environment | **BLOCK** | No burial condition or suffocation |
| 2364 | Environment | **BLOCK** | No rescue dig mechanic |
| 2365 | Environment | **BLOCK** | No collapse handler |
| 2366 | Environment | **BLOCK** | No wind system |
| 2367 | Environment | **BLOCK** | No wind ranged attack penalty |
| 2368 | Environment | **BLOCK** | No wind vs flying |
| 2369 | Environment | **BLOCK** | No wind ground movement check |
| 2370 | Environment | **BLOCK** | No underwater visibility |
| 2371 | Environment | **BLOCK** | No current swimming modifier |
| 2372 | Environment | **BLOCK** | No current displacement |
| 2373 | Hazards | **PASS** | ContentRegistry: stealth_dc required; RoomStateService hides traps until detected |
| 2374 | Hazards | **PARTIAL** | processSearch() rolls Perception but not auto-triggered on entry; not secret |
| 2375 | Hazards | **BLOCK** | No min-proficiency gating in detection |
| 2376 | Hazards | **BLOCK** | detect_magic not wired to hazard reveal |
| 2377 | Hazards | **PARTIAL** | Trap hidden until detected; no auto-trigger on undetected entry |
| 2378 | Hazards | **PARTIAL** | processInteract() would be hook; not wired |
| 2379 | Hazards | **BLOCK** | No simple/complex hazard type |
| 2380 | Hazards | **BLOCK** | No complex hazard routine / initiative join |
| 2381 | Hazards | **BLOCK** | No routine field in trap schema |
| 2382 | Hazards | **BLOCK** | No hazard initiative join |
| 2383 | Hazards | **BLOCK** | No reset_condition field |
| 2384 | Hazards | **PASS** | ContentRegistry: disable_dc required (schema); action not yet wired |
| 2385 | Hazards | **BLOCK** | No disable handler; no crit-fail trigger |
| 2386 | Hazards | **BLOCK** | No multi-success disable tracking |
| 2387 | Hazards | **BLOCK** | No min-proficiency field for disabling |
| 2388 | Hazards | **PARTIAL** | detected flag present in RoomStateService; no disable handler to enforce |
| 2389 | Hazards | **BLOCK** | Schema missing AC, saves, hardness, HP, BT |
| 2390 | Hazards | **BLOCK** | No HP tracking for hazards |
| 2391 | Hazards | **BLOCK** | Hazards not valid attack targets |
| 2392 | Hazards | **BLOCK** | No hazard immunity flags |
| 2393 | Hazards | **BLOCK** | No spell_level / counteract_dc in hazard schema |
| 2394 | Hazards | **BLOCK** | No counteract handler |
| 2395 | Hazards | **BLOCK** | No hazard XP award |
| 2396 | Hazards | **BLOCK** | No hazard XP table |
| 2397 | NPC Social | **PARTIAL** | Attitude ladder modeled; no DC modifier lookup |

---

## Suggested Feature Pipeline (PM triage)

| Feature ID | REQs Covered | Priority |
|---|---|---|
| `dc-cr-environment-terrain` | 2351–2372 | MEDIUM — terrain sub-types + Athletics/Acrobatics triggers; wind; water; avalanche/burial |
| `dc-cr-hazard-system` | 2373–2396 | HIGH — simple/complex hazard full stat block, trigger/disable/HP wiring; hazard XP |
| `dc-cr-rest-watch-starvation` | 2346–2349 | LOW — watch scheduling, food/water tracking |
| `dc-cr-creature-identification` | 2331 | MEDIUM — recall_knowledge trait→skill routing (also flagged in ch10 previous run) |

**NPC social DC (REQ 2397):** covered by existing `dc-cr-skills-diplomacy-actions` (attitude→DC modifier table).

**Already in pipeline (previously identified) covering ch10 gaps:**
- `dc-cr-dc-rarity-spell-adjustment` → REQs 2375, 2387 (min proficiency checks)
- `dc-cr-skills-recall-knowledge` → REQ 2331

---

## Codebase Evidence

| File | Relevant Lines | Status |
|---|---|---|
| `ContentRegistry.php` | 412–425 (validateTrap: stealth_dc + disable_dc required) | PASS foundation |
| `RoomStateService.php` | 323–331 (hide trap until detected) | PASS foundation |
| `ExplorationPhaseHandler.php` | 611–645 (processSearch: Perception vs room search_dc) | PARTIAL |
| `ExplorationPhaseHandler.php` | 535–548 (processInteract: future TODO for traps) | BLOCK |
| `TerrainGeneratorService.php` | 28–85 (terrain types with difficult_terrain flags) | PARTIAL |
| `ExplorationPhaseHandler.php` | 790–810 (calculateTravelSpeed: terrain multipliers) | PARTIAL |
| `NpcPsychologyService.php` | 28–33 (ATTITUDE_LADDER), 769+ (shiftAttitude) | PARTIAL |
| `DowntimePhaseHandler.php` | 104–170 (long_rest/downtime_rest/retrain cases; no watch/starvation) | BLOCK |
